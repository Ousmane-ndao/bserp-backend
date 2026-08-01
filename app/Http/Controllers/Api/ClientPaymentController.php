<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Client;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request, Client $client): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 50), 100);

        $payments = Payment::query()
            ->where('client_id', $client->id)
            ->orderBy('date_paiement')
            ->orderBy('id')
            ->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    public function store(StoreClientPaymentRequest $request, Client $client): JsonResponse
    {
        $data = $request->validated();
        $montant = (float) ($data['amount'] ?? $data['montant']);
        $allowOverpayment = (bool) ($data['allow_overpayment'] ?? false);

        $this->paymentService->validateMontant($client, $montant, null, $allowOverpayment);

        $payment = Payment::query()->create([
            'client_id' => $client->id,
            'dossier_id' => $this->paymentService->resolveDossierId(
                $client,
                isset($data['dossier_id']) ? (int) $data['dossier_id'] : null,
            ),
            'montant' => $montant,
            'currency' => strtoupper(substr((string) config('currency.code'), 0, 3)),
            'methode' => $data['method'] ?? $data['methode'] ?? 'Virement',
            'commentaire' => $data['commentaire'] ?? null,
            'avance_numero' => $this->paymentService->nextAvanceNumero($client),
            'date_paiement' => $data['paid_at'] ?? $data['date_paiement'] ?? now()->toDateString(),
        ]);

        $this->paymentService->logAudit(
            $payment,
            'created',
            null,
            $this->paymentService->snapshot($payment),
            $request->user()?->id,
        );

        return (new PaymentResource($payment))
            ->additional(['summary' => $this->paymentService->getSummary($client->fresh())])
            ->response()
            ->setStatusCode(201);
    }

    public function summary(Client $client): JsonResponse
    {
        return response()->json([
            'data' => $this->paymentService->getSummary($client),
        ]);
    }
}
