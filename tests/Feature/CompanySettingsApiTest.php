<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanySettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $roleKey): User
    {
        $role = Role::query()->firstOrCreate(['name' => RoleMapper::toDbName($roleKey)]);
        $employee = Employee::query()->create([
            'name' => ucfirst($roleKey).' Test',
            'email' => $roleKey.'.'.uniqid().'@test.com',
            'role_id' => $role->id,
            'telephone' => '0000000000',
            'statut' => 'Actif',
        ]);

        return User::query()->create([
            'name' => $employee->name,
            'email' => $employee->email,
            'password' => 'password',
            'employee_id' => $employee->id,
        ])->fresh(['employee.role']);
    }

    public function test_directrice_can_read_and_update_company_settings(): void
    {
        Sanctum::actingAs($this->actingUser('directrice'));

        $this->getJson('/api/settings/company')->assertOk()
            ->assertJsonPath('company_name', 'BSERP');

        $this->putJson('/api/settings/company', [
            'company_name' => 'BSERP Groupe',
            'address' => 'Rue de la Paix',
            'city' => 'Casablanca',
            'country' => 'Maroc',
        ])->assertOk()->assertJsonPath('company_name', 'BSERP Groupe');
    }

    public function test_commercial_cannot_manage_company_settings(): void
    {
        Sanctum::actingAs($this->actingUser('commercial'));

        $this->getJson('/api/settings/company')->assertForbidden();
    }
}
