<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'libelle' => $this->libelle,
            'amount' => (string) $this->montant,
            'currency' => $this->currency ?? config('currency.code'),
            'categorie' => $this->categorie,
            'spentAt' => $this->date_depense?->format('Y-m-d'),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
