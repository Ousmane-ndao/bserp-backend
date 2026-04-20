<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payment::query();

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        $perPage = min($request->integer('per_page', 20), 100);

        return PaymentResource::collection($query->orderByDesc('id')->paginate($perPage));
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $payment = Payment::query()->create([
            'client_id' => $data['client_id'],
            'montant' => $data['amount'] ?? $data['montant'],
            'currency' => strtoupper(substr((string) ($data['currency'] ?? config('currency.code')), 0, 3)),
            'methode' => $data['method'] ?? $data['methode'] ?? 'Virement',
            'date_paiement' => $data['paid_at'] ?? $data['date_paiement'] ?? now()->toDateString(),
        ]);

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Payment $payment): PaymentResource
    {
        return new PaymentResource($payment);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('client_id', $data)) {
            $payment->client_id = $data['client_id'];
        }
        if (array_key_exists('amount', $data) || array_key_exists('montant', $data)) {
            $payment->montant = $data['amount'] ?? $data['montant'] ?? $payment->montant;
        }
        if (array_key_exists('method', $data) || array_key_exists('methode', $data)) {
            $payment->methode = $data['method'] ?? $data['methode'] ?? $payment->methode;
        }
        if (array_key_exists('paid_at', $data) || array_key_exists('date_paiement', $data)) {
            $payment->date_paiement = $data['paid_at'] ?? $data['date_paiement'] ?? $payment->date_paiement;
        }
        if (array_key_exists('currency', $data) && $data['currency'] !== null) {
            $payment->currency = strtoupper(substr((string) $data['currency'], 0, 3));
        }

        $payment->save();

        return (new PaymentResource($payment->fresh()))->response();
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $payment->delete();

        return response()->json(null, 204);
    }
}
