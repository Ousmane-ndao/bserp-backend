<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Services\InvoiceDeliveryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

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

        return response()->json([
            'data' => (new InvoiceResource($invoice))->toArray($request),
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
        $preview = app(InvoiceDeliveryService::class)->preview($invoice);
        $pdfUrl = URL::temporarySignedRoute('invoices.public-pdf', now()->addDays(7), ['invoice' => $invoice->id]);
        $message = $this->buildInvoiceWhatsappMessage($invoice->loadMissing('client'), $pdfUrl);
        $whatsappId = $preview['whatsappId'] ?? null;
        $whatsappUrl = $whatsappId ? 'https://wa.me/'.$whatsappId.'?text='.rawurlencode($message) : null;

        return response()->json([
            'data' => [
                'pdfUrl' => $pdfUrl,
                'whatsappUrl' => $whatsappUrl,
                'canWhatsapp' => $whatsappUrl !== null,
                'hasEmail' => ! empty($preview['email']),
                'preview' => $preview,
            ],
        ]);
    }

    public function deliveryPreview(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'data' => app(InvoiceDeliveryService::class)->preview($invoice),
        ]);
    }

    public function deliver(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:email,whatsapp,both'],
        ]);

        $payload = app(InvoiceDeliveryService::class)->deliver(
            $invoice,
            $validated['mode'],
            $request->user()?->id,
        );

        return response()->json(['data' => $payload]);
    }

    public function sendEmail(Invoice $invoice): JsonResponse
    {
        $result = app(InvoiceDeliveryService::class)->sendEmail($invoice, request()->user()?->id);
        if (! ($result['ok'] ?? false)) {
            return response()->json(['message' => $result['errorMessage'] ?? $result['label'] ?? 'Envoi e-mail impossible.'], 422);
        }

        return response()->json(['message' => $result['label'] ?? 'Facture envoyée par e-mail.']);
    }

    private function buildInvoiceWhatsappMessage(Invoice $invoice, string $pdfUrl): string
    {
        $receiver = trim((string) ($invoice->client?->prenom.' '.$invoice->client?->nom));
        $amount = number_format((float) $invoice->montant_ttc, 0, ',', ' ');
        $invoiceDate = $invoice->date_emission?->format('d/m/Y') ?? now()->format('d/m/Y');

        return "Bonjour {$receiver}, voici votre recu {$invoice->numero} du {$invoiceDate} pour {$amount} {$invoice->currency}. "
            ."Vous pouvez le telecharger ici: {$pdfUrl}. Merci - BS Consulting.";
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
