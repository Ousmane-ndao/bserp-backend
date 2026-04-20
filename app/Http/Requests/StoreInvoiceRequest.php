<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
            'numero' => ['nullable', 'string', 'max:32', 'unique:invoices,numero'],
            'date_emission' => ['required', 'date'],
            'date_echeance' => ['nullable', 'date'],
            'statut' => ['required', 'string', Rule::in([
                Invoice::STATUT_BROUILLON,
                Invoice::STATUT_ENVOYEE,
                Invoice::STATUT_PAYEE,
                Invoice::STATUT_ANNULEE,
            ])],
            'montant_ttc' => ['required', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('numero') && $this->input('numero') === '') {
            $this->merge(['numero' => null]);
        }
        if ($this->filled('amount') && ! $this->filled('montant_ttc')) {
            $this->merge(['montant_ttc' => $this->input('amount')]);
        }
    }
}
