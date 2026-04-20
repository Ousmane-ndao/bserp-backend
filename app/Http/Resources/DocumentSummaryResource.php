<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'nom' => $this->original_filename ?? basename($this->file_path),
            'type' => $this->type_document,
            'date' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
