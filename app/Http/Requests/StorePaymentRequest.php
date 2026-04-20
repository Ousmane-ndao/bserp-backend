<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
            'amount' => ['nullable', 'numeric', 'min:0', 'required_without:montant'],
            'montant' => ['nullable', 'numeric', 'min:0', 'required_without:amount'],
            'method' => ['nullable', 'string', 'max:255'],
            'methode' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'date_paiement' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
