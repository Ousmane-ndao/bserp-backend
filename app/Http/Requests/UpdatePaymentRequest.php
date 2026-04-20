<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
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
            'client_id' => ['sometimes', 'required', 'exists:clients,id'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'required_without:montant'],
            'montant' => ['sometimes', 'nullable', 'numeric', 'min:0', 'required_without:amount'],
            'method' => ['sometimes', 'nullable', 'string', 'max:255'],
            'methode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'date_paiement' => ['sometimes', 'nullable', 'date'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
        ];
    }
}
