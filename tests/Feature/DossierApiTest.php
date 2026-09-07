<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Destination;
use App\Models\Dossier;
use App\Models\Document;
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

    public function test_accueil_can_update_dossier(): void
    {
        $user = $this->userForRole('accueil');
        $dossier = $this->createDossier();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/dossiers/{$dossier->id}", [
            'statut' => 'Terminé',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.statut', 'Terminé');
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

    public function test_dashboard_counts_complete_dossiers_from_all_required_document_types(): void
    {
        $user = $this->userForRole('directrice');
        $dossier = $this->createDossier()->forceFill(['statut' => 'Refusé']);
        $dossier->save();

        foreach ([
            'CNI ou Passeport',
            'Bulletins de notes',
            'Diplôme Bac',
            'Certificat de scolarité',
            'Relevé de notes Bac',
            'Travail',
            'Photo d’identité',
            'CV',
        ] as $type) {
            Document::query()->create([
                'client_id' => $dossier->client_id,
                'dossier_id' => $dossier->id,
                'type_document' => $type,
                'file_path' => 'test/'.$type.'.pdf',
                'original_filename' => $type.'.pdf',
            ]);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('dossiers_complets', 1)
            ->assertJsonPath('documents_manquants', 0);
    }

    public function test_dossier_list_honors_page_and_filters(): void
    {
        $user = $this->userForRole('directrice');
        foreach (range(1, 3) as $_) {
            $this->createDossier();
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dossiers?page=2&per_page=2&statut=En%20cours');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(1, 'data');

        $this->assertStringContainsString('page=1', (string) $response->json('meta.prev_page_url'));
        $this->assertStringContainsString('statut=En%20cours', (string) $response->json('meta.prev_page_url'));
        $this->assertNull($response->json('meta.next_page_url'));
    }

    public function test_dossier_list_pages_do_not_overlap(): void
    {
        $user = $this->userForRole('directrice');
        foreach (range(1, 15) as $_) {
            $this->createDossier();
        }

        Sanctum::actingAs($user);

        $page1 = $this->getJson('/api/dossiers?page=1&per_page=10&sort_by=reference&sort_dir=desc');
        $page2 = $this->getJson('/api/dossiers?page=2&per_page=10&sort_by=reference&sort_dir=desc');

        $page1->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.current_page', 1);
        $page2->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.current_page', 2);

        $ids1 = collect($page1->json('data'))->pluck('id');
        $ids2 = collect($page2->json('data'))->pluck('id');

        $this->assertCount(0, $ids1->intersect($ids2));
    }

    public function test_dossier_list_reaches_page_25(): void
    {
        $user = $this->userForRole('directrice');
        foreach (range(1, 248) as $_) {
            $this->createDossier();
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dossiers?page=25&per_page=10');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 25)
            ->assertJsonPath('meta.last_page', 25)
            ->assertJsonPath('meta.total', 248)
            ->assertJsonCount(8, 'data');
    }
}
