<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
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
            'libelle' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0', 'required_without:montant'],
            'montant' => ['nullable', 'numeric', 'min:0', 'required_without:amount'],
            'spent_at' => ['nullable', 'date', 'required_without:date_depense'],
            'date_depense' => ['nullable', 'date', 'required_without:spent_at'],
            'categorie' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
