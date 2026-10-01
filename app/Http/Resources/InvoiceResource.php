<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'clientId' => (string) $this->client_id,
            'clientName' => trim((string) (($this->client?->prenom ?? '').' '.($this->client?->nom ?? ''))),
            'clientEmail' => $this->client?->email,
            'clientPhone' => $this->client?->telephone,
            'numero' => $this->numero,
            'dateEmission' => $this->date_emission?->format('Y-m-d'),
            'dateEcheance' => $this->date_echeance?->format('Y-m-d'),
            'statut' => $this->statut,
            'montantTtc' => (string) $this->montant_ttc,
            'currency' => $this->currency ?? config('currency.code'),
            'notes' => $this->notes,
            'creatorUserId' => $this->creator_user_id ? (string) $this->creator_user_id : null,
            'creatorName' => $this->creator?->employee?->name ?? $this->creator?->name,
            'creatorRole' => $this->creator_role ?? $this->creator?->employee?->role?->name,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
