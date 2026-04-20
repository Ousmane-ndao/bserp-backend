<?php

namespace App\Http\Requests;

use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
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
        $employee = $this->route('employee');
        $userId = $employee?->user?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
                Rule::unique('employees', 'email')->ignore($employee?->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'role' => ['sometimes', 'string', Rule::in(Roles::ALL)],
            'statut' => ['nullable', 'string', 'max:16'],
        ];
    }
}
