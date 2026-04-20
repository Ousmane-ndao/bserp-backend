<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
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
        $invoice = $this->route('invoice');
        $invoiceId = $invoice instanceof Invoice ? $invoice->id : 0;

        return [
            'client_id' => ['sometimes', 'required', 'exists:clients,id'],
            'numero' => ['sometimes', 'nullable', 'string', 'max:32', Rule::unique('invoices', 'numero')->ignore($invoiceId)],
            'date_emission' => ['sometimes', 'required', 'date'],
            'date_echeance' => ['sometimes', 'nullable', 'date'],
            'statut' => ['sometimes', 'required', 'string', Rule::in([
                Invoice::STATUT_BROUILLON,
                Invoice::STATUT_ENVOYEE,
                Invoice::STATUT_PAYEE,
                Invoice::STATUT_ANNULEE,
            ])],
            'montant_ttc' => ['sometimes', 'required', 'numeric', 'min:0'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('amount') && ! $this->filled('montant_ttc')) {
            $this->merge(['montant_ttc' => $this->input('amount')]);
        }
    }
}
