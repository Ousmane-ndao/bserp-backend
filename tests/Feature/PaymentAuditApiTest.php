<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Destination;
use App\Models\Dossier;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentAuditApiTest extends TestCase
{
    use RefreshDatabase;

    private function userForRole(string $frontendKey): User
    {
        $roleName = RoleMapper::toDbName($frontendKey);
        $role = Role::query()->firstOrCreate(['name' => $roleName]);
        $email = 'audit_'.uniqid('', true).'@test.com';

        $employee = Employee::query()->create([
            'name' => 'Audit '.$frontendKey,
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

    private function makePaymentWithLog(): array
    {
        $destination = Destination::query()->create([
            'name' => 'Dest Audit '.uniqid(),
            'region' => 'Afrique',
            'type_compte' => 'SIMPLE',
            'montant_total' => 150000,
        ]);

        $client = Client::query()->create([
            'prenom' => 'Audit',
            'nom' => 'Client',
            'email' => 'audit.client.'.uniqid('', true).'@test.com',
            'telephone' => '770000001',
            'destination_id' => $destination->id,
        ]);

        $dossier = Dossier::query()->create([
            'client_id' => $client->id,
            'reference' => 'D-2026-001',
            'statut' => 'En cours',
            'date_ouverture' => now()->toDateString(),
            'montant_total' => 150000,
            'solde_restant' => 150000,
        ]);

        $payment = Payment::query()->create([
            'client_id' => $client->id,
            'dossier_id' => $dossier->id,
            'montant' => 25000,
            'currency' => 'XOF',
            'methode' => 'Virement',
            'commentaire' => 'Paiement initial',
            'date_paiement' => now()->toDateString(),
        ]);

        $payment->auditLogs()->create([
            'client_id' => $client->id,
            'user_id' => null,
            'action' => 'created',
            'payload' => [
                'before' => null,
                'after' => ['montant' => '25000'],
            ],
            'created_at' => now(),
        ]);

        return [$payment, $client];
    }

    public function test_accounting_roles_can_list_payment_audit_logs(): void
    {
        [$payment] = $this->makePaymentWithLog();
        $user = $this->userForRole('comptable');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/payments/audit');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
        $this->assertSame('created', $response->json('data.0.action'));
        $this->assertSame((string) $payment->id, (string) $response->json('data.0.payment_id'));
    }

    public function test_commercial_cannot_access_payment_audit_logs(): void
    {
        $this->makePaymentWithLog();
        $user = $this->userForRole('commercial');
        Sanctum::actingAs($user);

        $this->getJson('/api/payments/audit')->assertForbidden();
    }
}
