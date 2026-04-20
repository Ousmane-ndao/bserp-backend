<?php

namespace App\Http\Requests;

use App\Models\Dossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDossierRequest extends FormRequest
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
        return [
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'statut' => ['sometimes', 'nullable', 'string', Rule::in(Dossier::STATUTS)],
            'date_ouverture' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
