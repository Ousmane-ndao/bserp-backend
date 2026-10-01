<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeAccessApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => RoleMapper::toDbName('directrice')]);
        $employee = Employee::query()->create([
            'name' => 'Directrice Test',
            'email' => 'directrice.'.uniqid().'@test.com',
            'role_id' => $role->id,
            'telephone' => '0000000000',
            'statut' => 'Actif',
        ]);

        $user = User::query()->create([
            'name' => $employee->name,
            'email' => $employee->email,
            'password' => 'password',
            'employee_id' => $employee->id,
        ]);

        return $user->fresh(['employee.role']);
    }

    public function test_employee_index_exposes_access_permissions(): void
    {
        $role = Role::query()->firstOrCreate(['name' => RoleMapper::toDbName('comptable')]);
        Employee::query()->create([
            'name' => 'Comptable Test',
            'email' => 'comptable.'.uniqid().'@test.com',
            'role_id' => $role->id,
            'telephone' => '1111111111',
            'statut' => 'Actif',
        ]);

        Sanctum::actingAs($this->actingAdmin());

        $response = $this->getJson('/api/employees?per_page=20');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertContains('comptabilite', $response->json('data.0.permissions'));
        $this->assertContains('dashboard', $response->json('data.0.permissions'));
    }
}
