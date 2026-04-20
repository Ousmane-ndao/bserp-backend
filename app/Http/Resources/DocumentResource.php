<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $client = $this->whenLoaded('client');

        return [
            'id' => (string) $this->id,
            'nom' => $this->original_filename ?? basename($this->file_path),
            'type' => $this->type_document,
            'client' => $client ? trim($client->prenom.' '.$client->nom) : null,
            'clientId' => (string) $this->client_id,
            'dossierId' => $this->dossier_id ? (string) $this->dossier_id : null,
            'taille' => $this->size_bytes,
            'date' => $this->created_at?->format('Y-m-d'),
            'mime' => $this->mime,
        ];
    }
}
