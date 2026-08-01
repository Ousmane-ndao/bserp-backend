<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payment::query()->with(['client', 'dossier']);

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('dossier_id')) {
            $query->where('dossier_id', $request->integer('dossier_id'));
        }

        $perPage = min($request->integer('per_page', 20), 100);

        return PaymentResource::collection(
            $query->orderByDesc('date_paiement')->orderByDesc('id')->paginate($perPage)
        );
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $client = Client::query()->findOrFail($data['client_id']);
        $montant = (float) ($data['amount'] ?? $data['montant']);
        $allowOverpayment = (bool) ($data['allow_overpayment'] ?? false);

        $dossierId = isset($data['dossier_id']) ? (int) $data['dossier_id'] : null;
        if (! $dossierId) {
            $dossierId = $client->dossiers()->orderBy('id')->value('id');
        }

        if (! $dossierId) {
            throw ValidationException::withMessages([
                'dossier_id' => ['Aucun dossier trouvé pour ce client.'],
            ]);
        }

        $dossier = Dossier::query()->findOrFail($dossierId);
        $this->paymentService->assertDossierBelongsToClient($dossier, $client);

        $payment = $this->paymentService->createPaymentAtomic([
            'dossier_id' => $dossier->id,
            'montant' => $montant,
            'methode' => $data['method'] ?? $data['methode'] ?? 'Virement',
            'commentaire' => $data['commentaire'] ?? null,
            'date_paiement' => $data['paid_at'] ?? $data['date_paiement'] ?? now()->toDateString(),
            'currency' => $data['currency'] ?? config('currency.code'),
            'allow_overpayment' => $allowOverpayment,
        ], $request->user()?->id);

        return (new PaymentResource($payment))
            ->additional(['summary' => $this->paymentService->getDossierSummary($dossier->fresh())])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Payment $payment): PaymentResource
    {
        return new PaymentResource($payment->load(['client', 'dossier']));
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $data = $request->validated();
        $payload = [];

        if (array_key_exists('client_id', $data)) {
            $payload['client_id'] = $data['client_id'];
        }
        if (array_key_exists('dossier_id', $data)) {
            $payload['dossier_id'] = $data['dossier_id'] ? (int) $data['dossier_id'] : null;
        }
        if (array_key_exists('amount', $data) || array_key_exists('montant', $data)) {
            $payload['montant'] = (float) ($data['amount'] ?? $data['montant']);
        }
        if (array_key_exists('method', $data) || array_key_exists('methode', $data)) {
            $payload['methode'] = $data['method'] ?? $data['methode'];
        }
        if (array_key_exists('commentaire', $data)) {
            $payload['commentaire'] = $data['commentaire'];
        }
        if (array_key_exists('paid_at', $data) || array_key_exists('date_paiement', $data)) {
            $payload['date_paiement'] = $data['paid_at'] ?? $data['date_paiement'];
        }
        if (array_key_exists('currency', $data)) {
            $payload['currency'] = $data['currency'];
        }
        if (array_key_exists('allow_overpayment', $data)) {
            $payload['allow_overpayment'] = (bool) $data['allow_overpayment'];
        }

        $updated = $this->paymentService->updatePaymentAtomic($payment, $payload, $request->user()?->id);

        $summary = $updated->dossier_id
            ? $this->paymentService->getDossierSummary($updated->dossier)
            : null;

        return (new PaymentResource($updated->load(['client', 'dossier'])))
            ->additional(['summary' => $summary])
            ->response();
    }

    public function destroy(Request $request, Payment $payment): JsonResponse
    {
        $this->paymentService->deletePaymentAtomic($payment, $request->user()?->id);

        return response()->json(null, 204);
    }
}
