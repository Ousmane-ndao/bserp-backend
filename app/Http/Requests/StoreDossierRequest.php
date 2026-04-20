<?php

namespace App\Http\Requests;

use App\Models\Dossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDossierRequest extends FormRequest
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
            'client_id' => ['required', 'exists:clients,id'],
            'type' => ['nullable', 'string', 'max:255'],
            'statut' => ['nullable', 'string', Rule::in(Dossier::STATUTS)],
            'date_ouverture' => ['nullable', 'date'],
        ];
    }
}
