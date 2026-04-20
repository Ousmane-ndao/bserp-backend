<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'clientId' => (string) $this->client_id,
            'amount' => (string) $this->montant,
            'currency' => $this->currency ?? config('currency.code'),
            'method' => $this->methode,
            'paidAt' => $this->date_paiement?->format('Y-m-d'),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
