<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentProgressRequest extends FormRequest
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
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'lettre_motivation' => ['sometimes', 'boolean'],
            'bulletins_enregistres' => ['sometimes', 'boolean'],
            'travail_effectue' => ['sometimes', 'boolean'],
            'notes_saisies' => ['sometimes', 'boolean'],
        ];
    }
}
