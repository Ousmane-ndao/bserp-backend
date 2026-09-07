<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\DocumentCatalog;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('dossier_id') && $this->input('dossier_id') !== null && $this->input('dossier_id') !== '') {
            $this->merge(['dossier_id' => (int) $this->input('dossier_id')]);
        }
        if ($this->has('type_document') && $this->input('type_document') === '') {
            $this->merge(['type_document' => null]);
        }
        if ($this->filled('type_document')) {
            $this->merge([
                'type_document' => DocumentCatalog::normalizeType($this->string('type_document')->toString()),
            ]);
        }
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
            'Relevé du Bac',
            'Travaux',
            'Photo',
            'CNI ou Passeport',
            'CV',
        ];

        return [
            'dossier_id' => ['required', 'integer', 'exists:dossiers,id'],
            'file' => ['required', 'file', 'max:15360'],
            'type_document' => ['nullable', 'string', Rule::in($types)],
        ];
    }
}
