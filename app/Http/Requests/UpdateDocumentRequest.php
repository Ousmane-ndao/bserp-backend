<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = [
            'Bulletins de notes',
            'Diplôme Bac',
            "Certificat d'inscription",
            'Relevé de notes Bac',
            'Travail',
            'Photo',
            'CNI ou Passeport',
            'CV',
        ];

        return [
            'statut' => ['sometimes', 'string', Rule::in(Document::STATUTS)],
            'type_document' => ['sometimes', 'nullable', 'string', Rule::in($types)],
            'file' => ['sometimes', 'file', 'max:15360'],
        ];
    }
}
