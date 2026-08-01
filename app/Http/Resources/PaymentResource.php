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
            'dossierId' => $this->dossier_id ? (string) $this->dossier_id : null,
            'amount' => (string) $this->montant,
            'currency' => $this->currency ?? config('currency.code'),
            'method' => $this->methode,
            'avanceNumero' => $this->avance_numero,
            'commentaire' => $this->commentaire,
            'paidAt' => $this->date_paiement?->format('Y-m-d'),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
