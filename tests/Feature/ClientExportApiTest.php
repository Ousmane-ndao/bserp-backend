<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Destination;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientExportApiTest extends TestCase
{
    use RefreshDatabase;

    private function userForRole(string $frontendKey): User
    {
        $roleName = RoleMapper::toDbName($frontendKey);
        $role = Role::query()->firstOrCreate(['name' => $roleName]);
        $email = 'export_'.uniqid('', true).'@test.com';
        $employee = Employee::query()->create([
            'name' => 'Export '.$frontendKey,
            'email' => $email,
            'role_id' => $role->id,
            'statut' => 'Actif',
        ]);
        $user = new User;
        $user->forceFill([
            'name' => $employee->name,
            'email' => $email,
            'password' => bcrypt('password'),
            'employee_id' => $employee->id,
        ])->save();

        return $user->fresh(['employee.role']);
    }

    public function test_authenticated_client_pdf_export_includes_all_clients(): void
    {
        $user = $this->userForRole('directrice');
        $destination = Destination::query()->create([
            'name' => 'Export Destination',
            'region' => 'Europe',
            'type_compte' => 'SIMPLE',
        ]);

        foreach (range(1, 21) as $number) {
            Client::query()->create([
                'prenom' => 'Client '.$number,
                'nom' => 'Export',
                'email' => 'client'.$number.'_'.uniqid('', true).'@test.com',
                'destination_id' => $destination->id,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->get('/api/exports/clients.pdf');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
