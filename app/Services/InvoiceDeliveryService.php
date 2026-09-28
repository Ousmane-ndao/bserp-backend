<?php

namespace App\Services;

use App\Mail\InvoiceSentToClientMail;
use App\Models\Invoice;
use App\Models\InvoiceDispatch;
use App\Support\ClientPhone;
use App\Support\OutboundMail;
use App\Support\PdfDocument;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InvoiceDeliveryService
{
    public function __construct(
        private readonly WhatsAppCloudService $whatsApp,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Invoice $invoice): array
    {
        $invoice->loadMissing('client');
        $client = $invoice->client;
        $email = trim((string) ($client?->email ?? ''));
        $phone = trim((string) ($client?->telephone ?? ''));

        $history = [];
        if (Schema::hasTable('invoice_dispatches')) {
            $rows = InvoiceDispatch::query()
                ->where('invoice_id', $invoice->id)
                ->orderByDesc('id')
                ->limit(24)
                ->get();
            $history = $this->dedupeDispatchRows($rows)
                ->take(12)
                ->map(fn (InvoiceDispatch $row) => $this->serializeDispatch($row))
                ->values()
                ->all();
        }

        return [
            'invoiceId' => (string) $invoice->id,
            'invoiceNumero' => $invoice->numero,
            'clientId' => (string) $invoice->client_id,
            'clientName' => trim((string) (($client?->prenom ?? '').' '.($client?->nom ?? ''))),
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'emailValid' => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
            'whatsappId' => ClientPhone::toWhatsappId($phone),
            'history' => $history,
        ];
    }

    /**
     * @return array{results: list<array<string, mixed>>, history: list<array<string, mixed>>}
     */
    public function deliver(Invoice $invoice, string $mode, ?int $userId): array
    {
        $invoice->loadMissing('client.destination');
        if ($invoice->client_id === null || $invoice->client === null) {
            throw ValidationException::withMessages([
                'client' => 'Cette facture n’est liée à aucun client.',
            ]);
        }
        if ($invoice->statut === Invoice::STATUT_ANNULEE) {
            throw ValidationException::withMessages([
                'statut' => 'Impossible d’envoyer une facture annulée.',
            ]);
        }

        $mode = strtolower(trim($mode));
        $channels = match ($mode) {
            'email' => ['email'],
            'whatsapp' => ['whatsapp'],
            'both', 'email_whatsapp', 'email+whatsapp' => ['email', 'whatsapp'],
            default => null,
        };
        if ($channels === null) {
            throw ValidationException::withMessages([
                'mode' => 'Choisissez e-mail, WhatsApp ou les deux.',
            ]);
        }

        $results = [];
        foreach ($channels as $channel) {
            $results[] = $channel === 'email'
                ? $this->sendEmail($invoice, $userId)
                : $this->sendWhatsapp($invoice, $userId);
        }

        $preview = $this->preview($invoice);

        return [
            'results' => $results,
            'history' => $preview['history'],
            'clientId' => (string) $invoice->client_id,
            'invoiceId' => (string) $invoice->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sendEmail(Invoice $invoice, ?int $userId): array
    {
        $invoice->loadMissing('client.destination');
        $client = $invoice->client;
        $email = trim((string) ($client?->email ?? ''));

        if ($email === '') {
            return $this->record($invoice, $userId, InvoiceDispatch::CHANNEL_EMAIL, InvoiceDispatch::STATUS_FAILED, null, "Aucune adresse e-mail n’est enregistrée sur la fiche de ce client.");
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->record($invoice, $userId, InvoiceDispatch::CHANNEL_EMAIL, InvoiceDispatch::STATUS_FAILED, $email, "L’adresse e-mail du client est invalide ({$email}).");
        }

        if (! OutboundMail::canDeliver()) {
            return $this->record(
                $invoice,
                $userId,
                InvoiceDispatch::CHANNEL_EMAIL,
                InvoiceDispatch::STATUS_FAILED,
                $email,
                OutboundMail::failureMessage()
            );
        }

        try {
            $pdfBinary = $this->pdfBinary($invoice);
            if ($pdfBinary === '') {
                return $this->record($invoice, $userId, InvoiceDispatch::CHANNEL_EMAIL, InvoiceDispatch::STATUS_FAILED, $email, 'Le PDF de la facture n’a pas pu être généré.');
            }
            $filename = 'facture-'.$invoice->numero.'.pdf';
            $name = trim((string) (($client->prenom ?? '').' '.($client->nom ?? '')));
            $body = $this->emailBody($invoice, $name !== '' ? $name : 'Madame, Monsieur');

            Mail::to($email, $name !== '' ? $name : null)->send(
                new InvoiceSentToClientMail($invoice, $name, $body, $pdfBinary, $filename)
            );

            if (! OutboundMail::canDeliver()) {
                return $this->record($invoice, $userId, InvoiceDispatch::CHANNEL_EMAIL, InvoiceDispatch::STATUS_FAILED, $email, OutboundMail::failureMessage());
            }

            return $this->record($invoice, $userId, InvoiceDispatch::CHANNEL_EMAIL, InvoiceDispatch::STATUS_SENT, $email, null);
        } catch (\Throwable $e) {
            return $this->record($invoice, $userId, InvoiceDispatch::CHANNEL_EMAIL, InvoiceDispatch::STATUS_FAILED, $email, $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function sendWhatsapp(Invoice $invoice, ?int $userId): array
    {
        $invoice->loadMissing('client.destination');
        $client = $invoice->client;
        $phone = trim((string) ($client?->telephone ?? ''));
        $whatsappId = ClientPhone::toWhatsappId($phone);

        if ($phone === '' || $whatsappId === null) {
            return $this->record(
                $invoice,
                $userId,
                InvoiceDispatch::CHANNEL_WHATSAPP,
                InvoiceDispatch::STATUS_FAILED,
                $phone !== '' ? $phone : null,
                'Aucun numéro de téléphone valide n’est enregistré sur la fiche de ce client.'
            );
        }

        $name = trim((string) (($client->prenom ?? '').' '.($client->nom ?? '')));

        if (! $this->whatsApp->isConfigured()) {
            return $this->record(
                $invoice,
                $userId,
                InvoiceDispatch::CHANNEL_WHATSAPP,
                InvoiceDispatch::STATUS_FAILED,
                $whatsappId,
                'Le service WhatsApp n’est pas configuré sur le serveur. Impossible de vérifier ni d’envoyer le PDF.'
            );
        }

        $caption = $this->whatsappCaption($invoice, $name !== '' ? $name : 'Madame, Monsieur');
        $pdfBinary = $this->pdfBinary($invoice);
        $filename = 'facture-'.$invoice->numero.'.pdf';

        $sent = $this->whatsApp->sendPdfDocument($whatsappId, $pdfBinary, $filename, $caption);
        if ($sent['ok']) {
            return $this->record($invoice, $userId, InvoiceDispatch::CHANNEL_WHATSAPP, InvoiceDispatch::STATUS_SENT, $whatsappId, null);
        }

        $error = $sent['not_on_whatsapp']
            ? 'le numéro n’est pas associé à WhatsApp'
            : (string) $sent['message'];

        return $this->record($invoice, $userId, InvoiceDispatch::CHANNEL_WHATSAPP, InvoiceDispatch::STATUS_FAILED, $whatsappId, $error);
    }

    /**
     * @return array<string, mixed>
     */
    private function record(
        Invoice $invoice,
        ?int $userId,
        string $channel,
        string $status,
        ?string $recipient,
        ?string $error,
        ?string $okLabel = null,
    ): array {
        $label = $this->resultLabel($channel, $status === InvoiceDispatch::STATUS_SENT, $error);

        if (Schema::hasTable('invoice_dispatches')) {
            $duplicate = InvoiceDispatch::query()
                ->where('invoice_id', $invoice->id)
                ->where('channel', $channel)
                ->where('status', $status)
                ->where('recipient', $recipient)
                ->where('created_at', '>=', now()->subSeconds(4))
                ->orderByDesc('id')
                ->first();

            $row = $duplicate ?? InvoiceDispatch::query()->create([
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'user_id' => $userId,
                'channel' => $channel,
                'status' => $status,
                'recipient' => $recipient,
                'error_message' => $error,
            ]);

            $payload = $this->serializeDispatch($row);
            $payload['ok'] = $status === InvoiceDispatch::STATUS_SENT;
            $payload['label'] = $okLabel ?? $label;

            return $payload;
        }

        return [
            'id' => 'tmp',
            'channel' => $channel,
            'status' => $status,
            'recipient' => $recipient,
            'errorMessage' => $error,
            'sentAt' => now()->format('d/m/Y H:i'),
            'sentAtIso' => now()->toIso8601String(),
            'ok' => $status === InvoiceDispatch::STATUS_SENT,
            'label' => $okLabel ?? $label,
        ];
    }

    private function resultLabel(string $channel, bool $ok, ?string $error): string
    {
        $name = $channel === InvoiceDispatch::CHANNEL_EMAIL ? 'E-mail' : 'WhatsApp';
        if ($ok) {
            return $name.' : Envoyé';
        }

        $reason = trim((string) $error);
        if ($reason === '') {
            $reason = 'échec';
        }

        return $name.' : Échec — '.$reason;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, InvoiceDispatch>  $rows
     * @return \Illuminate\Support\Collection<int, InvoiceDispatch>
     */
    private function dedupeDispatchRows(\Illuminate\Support\Collection $rows): \Illuminate\Support\Collection
    {
        $kept = [];
        foreach ($rows as $row) {
            $prev = $kept === [] ? null : $kept[array_key_last($kept)];
            if (
                $prev instanceof InvoiceDispatch
                && $prev->channel === $row->channel
                && $prev->status === $row->status
                && $prev->recipient === $row->recipient
                && $prev->created_at && $row->created_at
                && abs($prev->created_at->diffInSeconds($row->created_at)) <= 4
            ) {
                continue;
            }
            $kept[] = $row;
        }

        return collect($kept);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDispatch(InvoiceDispatch $row): array
    {
        return [
            'id' => (string) $row->id,
            'channel' => $row->channel,
            'status' => $row->status,
            'recipient' => $row->recipient,
            'errorMessage' => $row->error_message,
            'sentAt' => $row->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'sentAtIso' => $row->created_at?->toIso8601String(),
        ];
    }

    private function pdfBinary(Invoice $invoice): string
    {
        $invoice->loadMissing('client.destination');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'company' => \App\Models\CompanySetting::query()->first(),
        ])->setPaper('a4');

        return $pdf->output();
    }

    private function emailBody(Invoice $invoice, string $name): string
    {
        $amount = number_format((float) $invoice->montant_ttc, 0, ',', ' ').' '.($invoice->currency ?? 'XOF');
        $date = $invoice->date_emission?->format('d/m/Y') ?? now()->format('d/m/Y');
        $brand = PdfDocument::brand(null)['name'];

        return "Bonjour {$name},\n\n"
            ."Nous vous remercions pour votre paiement.\n"
            ."Veuillez trouver ci-joint votre facture n° {$invoice->numero} d’un montant de {$amount}, émise le {$date}.\n\n"
            ."Pour toute question, n’hésitez pas à nous contacter.\n\n"
            ."Cordialement,\n"
            ."L’équipe {$brand}";
    }

    private function whatsappCaption(Invoice $invoice, string $name): string
    {
        $amount = number_format((float) $invoice->montant_ttc, 0, ',', ' ').' '.($invoice->currency ?? 'XOF');
        $date = $invoice->date_emission?->format('d/m/Y') ?? now()->format('d/m/Y');
        $brand = PdfDocument::brand(null)['name'];

        return "Bonjour {$name}, merci pour votre paiement. Voici votre facture {$invoice->numero} ({$amount}) du {$date}. Cordialement — {$brand}.";
    }
}
