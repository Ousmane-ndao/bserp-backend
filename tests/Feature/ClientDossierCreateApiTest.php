<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientDossierCreateApiTest extends TestCase
{
    use RefreshDatabase;

    private function userForRole(string $frontendKey): User
    {
        $roleName = RoleMapper::toDbName($frontendKey);
        $role = Role::query()->firstOrCreate(['name' => $roleName]);
        $suffix = str_replace('.', '', uniqid('', true));
        $email = "test_{$frontendKey}_{$suffix}@test.com";
        $employee = Employee::query()->create([
            'name' => 'Test '.$frontendKey,
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

    private function destination(): Destination
    {
        return Destination::query()->firstOrCreate(
            ['name' => 'Test Destination'],
            ['region' => 'Afrique', 'type_compte' => 'SIMPLE']
        );
    }

    public function test_accueil_can_create_client(): void
    {
        $user = $this->userForRole('accueil');
        $destination = $this->destination();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/clients', [
            'prenom' => 'Marie',
            'nom' => 'Accueil',
            'email' => 'marie_'.uniqid('', true).'@test.com',
            'destination_id' => $destination->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.prenom', 'Marie');
    }

    public function test_accueil_can_create_dossier(): void
    {
        $user = $this->userForRole('accueil');
        $destination = $this->destination();

        Sanctum::actingAs($user);

        $clientResponse = $this->postJson('/api/clients', [
            'prenom' => 'Jean',
            'nom' => 'Dossier',
            'email' => 'jean_'.uniqid('', true).'@test.com',
            'destination_id' => $destination->id,
        ]);
        $clientId = $clientResponse->json('data.id');

        $response = $this->postJson('/api/dossiers', [
            'client_id' => $clientId,
            'statut' => 'En cours',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.statut', 'En cours');
    }
}
