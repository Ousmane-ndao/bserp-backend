<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentAccountRequest extends FormRequest
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
            'email' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'campus_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'parcoursup_password' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
