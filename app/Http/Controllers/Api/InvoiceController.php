<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\CompanySetting;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Invoice::query()->with('client');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut')->toString());
        }

        $perPage = min($request->integer('per_page', 20), 100);

        return InvoiceResource::collection($query->orderByDesc('id')->paginate($perPage));
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $invoice = Invoice::query()->create([
            'client_id' => $data['client_id'],
            'numero' => $data['numero'] ?? null,
            'date_emission' => $data['date_emission'],
            'date_echeance' => $data['date_echeance'] ?? null,
            'statut' => $data['statut'],
            'montant_ttc' => $data['montant_ttc'],
            'currency' => strtoupper(substr((string) ($data['currency'] ?? config('currency.code')), 0, 3)),
            'notes' => $data['notes'] ?? null,
        ]);

        $invoice->load('client.destination');
        $delivery = $this->autoDeliverInvoice($invoice);

        return response()->json([
            'data' => (new InvoiceResource($invoice))->toArray($request),
            'delivery' => $delivery,
        ], 201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load('client'));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('client_id', $data)) {
            $invoice->client_id = $data['client_id'];
        }
        if (array_key_exists('numero', $data)) {
            $invoice->numero = $data['numero'] ?? $invoice->numero;
        }
        if (array_key_exists('date_emission', $data)) {
            $invoice->date_emission = $data['date_emission'];
        }
        if (array_key_exists('date_echeance', $data)) {
            $invoice->date_echeance = $data['date_echeance'];
        }
        if (array_key_exists('statut', $data)) {
            $invoice->statut = $data['statut'];
        }
        if (array_key_exists('montant_ttc', $data)) {
            $invoice->montant_ttc = $data['montant_ttc'];
        }
        if (array_key_exists('notes', $data)) {
            $invoice->notes = $data['notes'];
        }
        if (array_key_exists('currency', $data) && $data['currency'] !== null) {
            $invoice->currency = strtoupper(substr((string) $data['currency'], 0, 3));
        }

        $invoice->save();

        return (new InvoiceResource($invoice->fresh()->load('client')))->response();
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->delete();

        return response()->json(null, 204);
    }

    public function pdf(Invoice $invoice): Response
    {
        $pdf = $this->buildInvoicePdf($invoice);

        return $pdf->download('facture-'.$invoice->numero.'.pdf');
    }

    public function publicPdf(Request $request, Invoice $invoice): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Lien expiré ou invalide.');
        }

        $pdf = $this->buildInvoicePdf($invoice);

        return $pdf->download('recu-'.$invoice->numero.'.pdf');
    }

    public function shareLinks(Invoice $invoice): JsonResponse
    {
        $invoice->loadMissing('client');
        $client = $invoice->client;
        $pdfUrl = URL::temporarySignedRoute('invoices.public-pdf', now()->addDays(7), ['invoice' => $invoice->id]);

        $telephone = $client?->telephone ? preg_replace('/\D+/', '', (string) $client->telephone) : '';
        $receiver = trim((string) ($client?->prenom.' '.$client?->nom));
        $amount = number_format((float) $invoice->montant_ttc, 0, ',', ' ');
        $invoiceDate = $invoice->date_emission?->format('d/m/Y') ?? now()->format('d/m/Y');
        $message = "Bonjour {$receiver}, voici votre recu {$invoice->numero} du {$invoiceDate} pour {$amount} {$invoice->currency}. ";
        $message .= "Vous pouvez le telecharger ici: {$pdfUrl}. Merci - BS Consulting.";
        $whatsappUrl = $telephone !== '' ? 'https://wa.me/'.$telephone.'?text='.rawurlencode($message) : null;

        return response()->json([
            'data' => [
                'pdfUrl' => $pdfUrl,
                'whatsappUrl' => $whatsappUrl,
                'canWhatsapp' => $whatsappUrl !== null,
                'hasEmail' => ! empty($client?->email),
            ],
        ]);
    }

    public function sendEmail(Invoice $invoice): JsonResponse
    {
        $invoice->loadMissing('client');
        $client = $invoice->client;
        $email = trim((string) ($client?->email ?? ''));
        if ($email === '') {
            return response()->json(['message' => "Le client n'a pas d'adresse email."], 422);
        }

        $pdf = $this->buildInvoicePdf($invoice);
        $filename = 'recu-'.$invoice->numero.'.pdf';
        $receiver = trim((string) ($client?->prenom.' '.$client?->nom));
        $mailBody = $this->buildInvoiceNotificationMessage($invoice, URL::temporarySignedRoute('invoices.public-pdf', now()->addDays(7), ['invoice' => $invoice->id]));

        Mail::send([], [], function ($message) use ($email, $receiver, $invoice, $pdf, $filename, $mailBody) {
            $message->to($email, $receiver !== '' ? $receiver : null)
                ->subject('Recu '.$invoice->numero)
                ->text($mailBody)
                ->attachData($pdf->output(), $filename, ['mime' => 'application/pdf']);
        });

        return response()->json(['message' => 'Recu envoye par email.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function autoDeliverInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing('client');
        $client = $invoice->client;
        $email = trim((string) ($client?->email ?? ''));
        $pdfUrl = URL::temporarySignedRoute('invoices.public-pdf', now()->addDays(7), ['invoice' => $invoice->id]);

        if ($email !== '') {
            try {
                $pdf = $this->buildInvoicePdf($invoice);
                $filename = 'recu-'.$invoice->numero.'.pdf';
                $receiver = trim((string) ($client?->prenom.' '.$client?->nom));
                $mailBody = $this->buildInvoiceNotificationMessage($invoice, $pdfUrl);

                Mail::send([], [], function ($message) use ($email, $receiver, $invoice, $pdf, $filename, $mailBody) {
                    $message->to($email, $receiver !== '' ? $receiver : null)
                        ->subject('Recu '.$invoice->numero)
                        ->text($mailBody)
                        ->attachData($pdf->output(), $filename, ['mime' => 'application/pdf']);
                });

                return [
                    'channel' => 'email',
                    'status' => 'sent',
                    'message' => "Facture envoyée automatiquement par email à {$email}.",
                    'pdfUrl' => $pdfUrl,
                    'whatsappUrl' => null,
                ];
            } catch (Throwable $exception) {
                Log::error('Invoice auto email failed', [
                    'invoice_id' => $invoice->id,
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'channel' => 'email',
                    'status' => 'missing_contact',
                    'message' => "Échec de l'envoi email vers {$email}. Vérifiez la configuration de messagerie.",
                    'pdfUrl' => $pdfUrl,
                    'whatsappUrl' => null,
                ];
            }
        }

        return [
            'channel' => 'none',
            'status' => 'missing_contact',
            'message' => "Aucun email n'est enregistré pour ce client. Veuillez renseigner l'email avant d'envoyer la facture.",
            'pdfUrl' => $pdfUrl,
            'whatsappUrl' => null,
        ];
    }

    private function buildInvoiceWhatsappMessage(Invoice $invoice, string $pdfUrl): string
    {
        $receiver = trim((string) ($invoice->client?->prenom.' '.$invoice->client?->nom));
        $amount = number_format((float) $invoice->montant_ttc, 0, ',', ' ');
        $invoiceDate = $invoice->date_emission?->format('d/m/Y') ?? now()->format('d/m/Y');

        return "Bonjour {$receiver}, voici votre recu {$invoice->numero} du {$invoiceDate} pour {$amount} {$invoice->currency}. "
            ."Vous pouvez le telecharger ici: {$pdfUrl}. Merci - BS Consulting.";
    }

    private function buildInvoiceNotificationMessage(Invoice $invoice, string $pdfUrl): string
    {
        return "Bonjour,\n\n"
            ."Veuillez trouver ci-joint votre reçu {$invoice->numero}.\n"
            ."Lien de téléchargement: {$pdfUrl}\n\n"
            ."Cordialement,\nBS Consulting";
    }

    private function buildInvoicePdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->load('client.destination');
        $company = CompanySetting::query()->first();

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'company' => $company,
        ])->setPaper('a4');
    }
}
