<?php

namespace App\Http\Resources;

use App\Support\RoleMapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $employee = $this->relationLoaded('employee') ? $this->employee : null;
        $roleName = $employee && $employee->relationLoaded('role') && $employee->role
            ? $employee->role->name
            : null;

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'telephone' => $employee?->telephone,
            'role' => RoleMapper::toFrontendKey($roleName),
        ];
    }
}
