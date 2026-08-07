<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_naissance' => $this->input('date_naissance') ?: null,
            'date_ouverture' => $this->input('date_ouverture') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $clientId = $this->route('client')?->id ?? $this->route('client');

        return [
            'prenom' => ['sometimes', 'string', 'max:255'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'etablissement' => ['nullable', 'string', 'max:255'],
            'niveau_etude' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($clientId)],
            'destination_id' => ['sometimes', 'exists:destinations,id'],
            'date_ouverture' => ['nullable', 'date'],
            'statut' => ['nullable', 'string', 'max:32'],

            // ---- Champs du compte étudiant ----
            'account_email' => ['nullable', 'email', 'max:255'],
            'email_password' => ['nullable', 'string', 'max:255'],   // ← corrigé de gmail_password
            'campus_password' => ['nullable', 'string', 'max:255'],
            'parcoursup_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}