<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientPaymentRequest extends FormRequest
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
            'dossier_id' => ['nullable', 'exists:dossiers,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'required_without:montant'],
            'montant' => ['nullable', 'numeric', 'min:0.01', 'required_without:amount'],
            'method' => ['nullable', 'string', 'max:255'],
            'methode' => ['nullable', 'string', 'max:255'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'paid_at' => ['nullable', 'date'],
            'date_paiement' => ['nullable', 'date'],
            'allow_overpayment' => ['nullable', 'boolean'],
        ];
    }
}
