<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\InvoiceAuditLog;
use App\Services\InvoiceDeliveryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function audit(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 20), 100);

        $logs = InvoiceAuditLog::query()
            ->with(['invoice', 'client', 'user.employee.role'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $logs->getCollection()->map(function (InvoiceAuditLog $log): array {
            return [
                'id' => (string) $log->id,
                'invoice_id' => $log->invoice_id ? (string) $log->invoice_id : null,
                'client_id' => $log->client_id ? (string) $log->client_id : null,
                'user_id' => $log->user_id ? (string) $log->user_id : null,
                'action' => $log->action,
                'payload' => $log->payload ?? [],
                'created_at' => $log->created_at?->toIso8601String(),
                'invoice' => $log->invoice ? [
                    'id' => (string) $log->invoice->id,
                    'numero' => $log->invoice->numero,
                    'statut' => $log->invoice->statut,
                    'montant_ttc' => (string) $log->invoice->montant_ttc,
                ] : null,
                'user' => $log->user ? [
                    'id' => (string) $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'role' => $log->user->employee?->role?->name,
                ] : null,
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'links' => [
                'first' => $logs->url(1),
                'last' => $logs->url($logs->lastPage()),
                'prev' => $logs->previousPageUrl(),
                'next' => $logs->nextPageUrl(),
            ],
        ]);
    }

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
        $user = $request->user();
        $roleName = $user?->employee?->role?->name ?? 'Utilisateur';

        $invoice = Invoice::query()->create([
            'client_id' => $data['client_id'],
            'creator_user_id' => $user?->id,
            'creator_role' => $roleName,
            'numero' => $data['numero'] ?? null,
            'date_emission' => $data['date_emission'],
            'date_echeance' => $data['date_echeance'] ?? null,
            'statut' => $data['statut'],
            'montant_ttc' => $data['montant_ttc'],
            'currency' => strtoupper(substr((string) ($data['currency'] ?? config('currency.code')), 0, 3)),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logAudit($invoice, 'created', null, $this->snapshot($invoice), $user?->id);
        $invoice->load(['client.destination', 'creator.employee.role']);
        app(InvoiceDeliveryService::class)->notifyInternalTeam($invoice, $user?->id);

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
        $before = $this->snapshot($invoice);

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
        $this->logAudit($invoice, 'updated', $before, $this->snapshot($invoice), $request->user()?->id);

        return (new InvoiceResource($invoice->fresh()->load('client')))->response();
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $before = $this->snapshot($invoice);
        $userId = request()->user()?->id;
        $invoice->delete();
        $this->logAudit($invoice, 'deleted', $before, null, $userId);

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

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Invoice $invoice): array
    {
        return [
            'client_id' => $invoice->client_id,
            'numero' => $invoice->numero,
            'date_emission' => $invoice->date_emission?->format('Y-m-d'),
            'date_echeance' => $invoice->date_echeance?->format('Y-m-d'),
            'statut' => $invoice->statut,
            'montant_ttc' => (string) $invoice->montant_ttc,
            'currency' => $invoice->currency,
            'notes' => $invoice->notes,
            'creator_user_id' => $invoice->creator_user_id,
            'creator_role' => $invoice->creator_role,
        ];
    }

    private function logAudit(Invoice $invoice, string $action, ?array $old = null, ?array $new = null, ?int $userId = null): void
    {
        InvoiceAuditLog::query()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'user_id' => $userId,
            'action' => $action,
            'payload' => [
                'before' => $old,
                'after' => $new,
            ],
            'created_at' => now(),
        ]);
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
