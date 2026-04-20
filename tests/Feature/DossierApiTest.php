<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Destination;
use App\Models\Dossier;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DossierApiTest extends TestCase
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

    private function createDossier(): Dossier
    {
        $destination = Destination::query()->firstOrCreate(
            ['name' => 'Test Destination'],
            ['region' => 'Afrique', 'type_compte' => 'SIMPLE']
        );
        $client = Client::query()->create([
            'prenom' => 'Jean',
            'nom' => 'Test',
            'email' => 'client_'.uniqid('', true).'@test.com',
            'destination_id' => $destination->id,
        ]);

        return Dossier::query()->create([
            'client_id' => $client->id,
            'reference' => 'D-TEST-'.uniqid(),
            'type' => 'Visa',
            'statut' => 'En cours',
            'date_ouverture' => now()->toDateString(),
        ]);
    }

    public function test_commercial_can_update_dossier(): void
    {
        $user = $this->userForRole('commercial');
        $dossier = $this->createDossier();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/dossiers/{$dossier->id}", [
            'type' => 'Visa étudiant',
            'statut' => 'Terminé',
            'date_ouverture' => '2026-01-15',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'Visa étudiant')
            ->assertJsonPath('data.statut', 'Terminé')
            ->assertJsonPath('data.date', '2026-01-15');

        $this->assertDatabaseHas('dossiers', [
            'id' => $dossier->id,
            'statut' => 'Terminé',
            'type' => 'Visa étudiant',
        ]);
    }

    public function test_accueil_cannot_update_dossier(): void
    {
        $user = $this->userForRole('accueil');
        $dossier = $this->createDossier();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/dossiers/{$dossier->id}", [
            'statut' => 'Terminé',
        ]);

        $response->assertStatus(403);
    }

    public function test_commercial_cannot_delete_dossier(): void
    {
        $user = $this->userForRole('commercial');
        $dossier = $this->createDossier();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/dossiers/{$dossier->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('dossiers', ['id' => $dossier->id]);
    }

    public function test_informaticien_can_delete_dossier(): void
    {
        $user = $this->userForRole('informaticien');
        $dossier = $this->createDossier();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/dossiers/{$dossier->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Dossier supprimé.');
        $this->assertDatabaseMissing('dossiers', ['id' => $dossier->id]);
    }

    public function test_guest_cannot_update_dossier(): void
    {
        $dossier = $this->createDossier();

        $response = $this->putJson("/api/dossiers/{$dossier->id}", [
            'statut' => 'Terminé',
        ]);

        $response->assertUnauthorized();
    }
}
