<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Destination;
use App\Models\Dossier;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\RoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DossierPaymentApiTest extends TestCase
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

    /**
     * @return array{client: Client, dossier: Dossier}
     */
    private function createClientWithDossier(float $montantTotal = 272500.00): array
    {
        $destination = Destination::query()->create([
            'name' => 'Europe Test '.uniqid('', true),
            'region' => 'Europe',
            'type_compte' => 'SIMPLE',
            'montant_total' => $montantTotal,
        ]);

        $client = Client::query()->create([
            'prenom' => 'Jean',
            'nom' => 'Dupont',
            'email' => 'client_'.uniqid('', true).'@test.com',
            'destination_id' => $destination->id,
        ]);

        $dossier = Dossier::query()->create([
            'client_id' => $client->id,
            'reference' => 'D-TEST-'.uniqid(),
            'statut' => 'En cours',
            'date_ouverture' => now()->toDateString(),
            'montant_total' => $montantTotal,
            'solde_restant' => $montantTotal,
        ]);

        return ['client' => $client, 'dossier' => $dossier];
    }

    public function test_can_create_sequential_payments_for_dossier(): void
    {
        $user = $this->userForRole('comptable');
        ['client' => $client, 'dossier' => $dossier] = $this->createClientWithDossier();

        Sanctum::actingAs($user);

        $first = $this->postJson("/api/clients/{$client->id}/dossiers/{$dossier->id}/payments", [
            'amount' => 100000,
            'method' => 'Virement',
            'paid_at' => '2026-07-01',
        ]);

        $first->assertCreated()
            ->assertJsonPath('data.avanceNumero', 'A1')
            ->assertJsonPath('summary.soldeRestant', '172500.00')
            ->assertJsonPath('summary.statutPaiement', PaymentService::STATUT_PARTIEL);

        $second = $this->postJson("/api/clients/{$client->id}/dossiers/{$dossier->id}/payments", [
            'amount' => 172500,
            'method' => 'Espèces',
        ]);

        $second->assertCreated()
            ->assertJsonPath('data.avanceNumero', 'A2')
            ->assertJsonPath('summary.statutPaiement', PaymentService::STATUT_PAYE);

        $this->assertDatabaseHas('dossiers', [
            'id' => $dossier->id,
            'solde_restant' => '0.00',
        ]);
    }

    public function test_blocks_overpayment_without_flag(): void
    {
        $user = $this->userForRole('comptable');
        ['client' => $client, 'dossier' => $dossier] = $this->createClientWithDossier(1000);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/clients/{$client->id}/dossiers/{$dossier->id}/payments", [
            'amount' => 1500,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['montant']);
    }

    public function test_allows_overpayment_when_flag_is_set(): void
    {
        $user = $this->userForRole('comptable');
        ['client' => $client, 'dossier' => $dossier] = $this->createClientWithDossier(1000);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/clients/{$client->id}/dossiers/{$dossier->id}/payments", [
            'amount' => 1500,
            'allow_overpayment' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('summary.statutPaiement', PaymentService::STATUT_TROP_PERCU);
    }

    public function test_rejects_payment_when_dossier_does_not_belong_to_client(): void
    {
        $user = $this->userForRole('comptable');
        ['client' => $client] = $this->createClientWithDossier();
        ['dossier' => $otherDossier] = $this->createClientWithDossier();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/clients/{$client->id}/dossiers/{$otherDossier->id}/payments", [
            'amount' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dossier']);
    }

    public function test_payment_summary_endpoint_returns_dossier_totals(): void
    {
        $user = $this->userForRole('comptable');
        ['client' => $client, 'dossier' => $dossier] = $this->createClientWithDossier(5000);

        Payment::query()->create([
            'client_id' => $client->id,
            'dossier_id' => $dossier->id,
            'montant' => 2000,
            'currency' => 'XOF',
            'methode' => 'Virement',
            'avance_numero' => 'A1',
            'date_paiement' => now()->toDateString(),
        ]);

        $dossier->recalculateSolde();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/clients/{$client->id}/dossiers/{$dossier->id}/payment-summary");

        $response->assertOk()
            ->assertJsonPath('data.montantTotal', '5000.00')
            ->assertJsonPath('data.totalPaye', '2000.00')
            ->assertJsonPath('data.soldeRestant', '3000.00')
            ->assertJsonPath('data.statutPaiement', PaymentService::STATUT_PARTIEL);
    }

    public function test_delete_payment_recalculates_dossier_balance(): void
    {
        $user = $this->userForRole('comptable');
        ['client' => $client, 'dossier' => $dossier] = $this->createClientWithDossier(3000);

        Sanctum::actingAs($user);

        $create = $this->postJson("/api/clients/{$client->id}/dossiers/{$dossier->id}/payments", [
            'amount' => 1000,
        ]);
        $paymentId = $create->json('data.id');

        $this->deleteJson("/api/payments/{$paymentId}")->assertNoContent();

        $this->assertDatabaseHas('dossiers', [
            'id' => $dossier->id,
            'solde_restant' => '3000.00',
        ]);
    }

    public function test_payments_export_json_returns_filtered_data(): void
    {
        $user = $this->userForRole('comptable');
        ['client' => $client, 'dossier' => $dossier] = $this->createClientWithDossier();

        Payment::query()->create([
            'client_id' => $client->id,
            'dossier_id' => $dossier->id,
            'montant' => 500,
            'currency' => 'XOF',
            'methode' => 'Virement',
            'avance_numero' => 'A1',
            'date_paiement' => '2026-07-10',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/exports/payments?client_id='.$client->id);

        $response->assertOk()
            ->assertJsonPath('data.payments.0.avanceNumero', 'A1')
            ->assertJsonPath('data.summary.0.dossier_reference', $dossier->reference);
    }
}
