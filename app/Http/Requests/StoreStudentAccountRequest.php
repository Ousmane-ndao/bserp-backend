<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentAccountRequest extends FormRequest
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
            'email' => ['nullable', 'string', 'max:150'],
            'email_password' => ['nullable', 'string', 'max:255'],
            'campus_password' => ['nullable', 'string', 'max:255'],
            'parcoursup_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
