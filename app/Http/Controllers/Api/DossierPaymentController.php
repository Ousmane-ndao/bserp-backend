<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DossierPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request, Client $client, Dossier $dossier): AnonymousResourceCollection
    {
        $this->paymentService->assertDossierBelongsToClient($dossier, $client);

        $perPage = min($request->integer('per_page', 50), 100);

        $payments = Payment::query()
            ->where('dossier_id', $dossier->id)
            ->orderBy('date_paiement')
            ->orderBy('id')
            ->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    public function store(StoreClientPaymentRequest $request, Client $client, Dossier $dossier): JsonResponse
    {
        $this->paymentService->assertDossierBelongsToClient($dossier, $client);

        $data = $request->validated();
        $montant = (float) ($data['amount'] ?? $data['montant']);

        $payment = $this->paymentService->createPaymentAtomic([
            'dossier_id' => $dossier->id,
            'montant' => $montant,
            'methode' => $data['method'] ?? $data['methode'] ?? 'Virement',
            'commentaire' => $data['commentaire'] ?? null,
            'date_paiement' => $data['paid_at'] ?? $data['date_paiement'] ?? now()->toDateString(),
            'allow_overpayment' => (bool) ($data['allow_overpayment'] ?? false),
        ], $request->user()?->id);

        return (new PaymentResource($payment))
            ->additional(['summary' => $this->paymentService->getDossierSummary($dossier->fresh())])
            ->response()
            ->setStatusCode(201);
    }

    public function summary(Client $client, Dossier $dossier): JsonResponse
    {
        $this->paymentService->assertDossierBelongsToClient($dossier, $client);

        return response()->json([
            'data' => $this->paymentService->getDossierSummary($dossier->fresh()),
        ]);
    }
}
