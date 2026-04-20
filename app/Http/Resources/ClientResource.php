<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $destination = $this->whenLoaded('destination');

        return [
            'id' => (string) $this->id,
            'prenom' => $this->prenom,
            'nom' => $this->nom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'dateNaissance' => $this->date_naissance?->format('Y-m-d'),
            'etablissement' => $this->etablissement,
            'niveauEtude' => $this->niveau_etude,
            'destination' => $destination ? $destination->name : null,
            'destinationId' => $this->destination_id,
            'dateOuverture' => $this->date_ouverture?->format('Y-m-d'),
            'statut' => 'Actif',
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
