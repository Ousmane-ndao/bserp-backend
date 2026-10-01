<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Destination;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentAccessApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $roleKey): User
    {
        $role = Role::query()->firstOrCreate(['name' => RoleMapper::toDbName($roleKey)]);
        $employee = Employee::query()->create([
            'name' => ucfirst($roleKey).' Doc',
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

    public function test_directrice_can_list_documents(): void
    {
        $destination = Destination::query()->create([
            'name' => 'Destination Docs '.uniqid(),
            'region' => 'Casablanca',
            'type_compte' => 'SIMPLE',
            'montant_total' => 5000,
        ]);

        $client = Client::query()->create([
            'prenom' => 'Amine',
            'nom' => 'Test',
            'email' => 'amine.'.uniqid().'@test.com',
            'telephone' => '0600000000',
            'destination_id' => $destination->id,
        ]);
        $dossier = Dossier::query()->create([
            'client_id' => $client->id,
            'reference' => 'D-2026-010',
            'statut' => 'En cours',
            'date_ouverture' => now()->toDateString(),
            'montant_total' => 1000,
            'solde_restant' => 1000,
        ]);

        Document::query()->create([
            'client_id' => $client->id,
            'dossier_id' => $dossier->id,
            'type_document' => 'Passeport',
            'file_path' => 'documents/passeport.pdf',
            'original_filename' => 'passeport.pdf',
            'size_bytes' => 123,
            'mime' => 'application/pdf',
        ]);

        Sanctum::actingAs($this->actingUser('directrice'));

        $response = $this->getJson('/api/documents?per_page=20');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertSame('Passeport', $response->json('data.0.type'));
    }

    public function test_commercial_can_access_documents_module(): void
    {
        Sanctum::actingAs($this->actingUser('commercial'));

        $this->getJson('/api/documents?per_page=20')->assertOk();
    }
}
