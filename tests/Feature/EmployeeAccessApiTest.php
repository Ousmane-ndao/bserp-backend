<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    private function userForRole(string $roleKey): User
    {
        $role = Role::query()->firstOrCreate(['name' => RoleMapper::toDbName($roleKey)]);
        $email = $roleKey.'.'.uniqid().'@test.com';
        $employee = Employee::query()->create([
            'name' => ucfirst($roleKey).' Test',
            'email' => $email,
            'role_id' => $role->id,
            'telephone' => '0000000000',
            'statut' => 'Actif',
        ]);

        return User::query()->create([
            'name' => $employee->name,
            'email' => $email,
            'password' => 'OldPassword123',
            'employee_id' => $employee->id,
        ]);
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

    public function test_informaticien_can_update_any_employee_email_and_password(): void
    {
        $informaticien = $this->userForRole('informaticien');
        $targetUser = $this->userForRole('commercial');
        $targetEmployee = $targetUser->employee;
        Sanctum::actingAs($informaticien);

        $response = $this->putJson('/api/employees/'.$targetEmployee->id, [
            'email' => 'commercial.updated@test.com',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'commercial.updated@test.com');
        $this->assertSame('commercial.updated@test.com', $targetEmployee->fresh()->email);
        $this->assertSame('commercial.updated@test.com', $targetUser->fresh()->email);
        $this->assertTrue(Hash::check('NewPassword123', $targetUser->fresh()->password));
    }

    public function test_commercial_cannot_update_employee_email_or_password(): void
    {
        $commercial = $this->userForRole('commercial');
        $targetUser = $this->userForRole('comptable');
        Sanctum::actingAs($commercial);

        $this->putJson('/api/employees/'.$targetUser->employee->id, [
            'email' => 'unauthorized@test.com',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertForbidden();

        $this->assertNotSame('unauthorized@test.com', $targetUser->fresh()->email);
        $this->assertTrue(Hash::check('OldPassword123', $targetUser->fresh()->password));
    }
}
