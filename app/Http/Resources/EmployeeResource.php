<?php

namespace App\Http\Resources;

use App\Support\RoleMapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->whenLoaded('user');
        $role = $this->whenLoaded('role');
        $roleName = $role ? $role->name : ($this->role ? $this->role->name : null);
        $permissions = RoleMapper::permissionsForRole($roleName);

        return [
            'id' => (string) $this->id,
            'nom' => $this->name,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'role' => RoleMapper::toFrontendKey($roleName),
            'permissions' => $permissions,
            'access' => $permissions,
            'statut' => $this->statut,
        ];
    }
}
