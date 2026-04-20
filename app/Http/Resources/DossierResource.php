<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DossierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $client = $this->whenLoaded('client');
        $destination = $client && $client->relationLoaded('destination')
            ? $client->destination
            : null;

        $docCount = isset($this->resource->documents_count)
            ? (int) $this->resource->documents_count
            : ($this->relationLoaded('documents') ? $this->documents->count() : 0);

        return [
            'id' => (string) $this->id,
            'reference' => $this->reference,
            'client' => $client ? trim($client->prenom.' '.$client->nom) : null,
            'clientId' => (string) $this->client_id,
            'clientEmail' => $client?->email,
            'clientTelephone' => $client?->telephone,
            'destination' => $destination?->name,
            'type' => $this->type,
            'statut' => $this->statut,
            'date' => $this->date_ouverture?->format('Y-m-d') ?? $this->created_at?->format('Y-m-d'),
            'documentCount' => $docCount,
            'documents' => DocumentSummaryResource::collection($this->whenLoaded('documents')),
        ];
    }
}
