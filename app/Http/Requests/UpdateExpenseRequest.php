<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
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
            'libelle' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'required_without:montant'],
            'montant' => ['sometimes', 'nullable', 'numeric', 'min:0', 'required_without:amount'],
            'spent_at' => ['sometimes', 'nullable', 'date', 'required_without:date_depense'],
            'date_depense' => ['sometimes', 'nullable', 'date', 'required_without:spent_at'],
            'categorie' => ['sometimes', 'nullable', 'string', 'max:120'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
        ];
    }
}
