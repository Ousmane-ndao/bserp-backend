<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommercialActivityApiTest extends TestCase
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

    public function test_commercial_can_create_and_list_own_activity(): void
    {
        $user = $this->userForRole('commercial');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/commercial-activities', [
            'type' => 'Appel',
            'date' => '2026-10-01',
            'time' => '09:30',
            'client_name' => 'Client Test',
            'result' => 'Entretien positif',
            'objective' => 'Prospection',
            'commentary' => 'Premier contact réussi',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'Appel');
        $response->assertJsonPath('data.commercial_name', $user->name);

        $list = $this->getJson('/api/commercial-activities');
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));
    }

    public function test_commercial_cannot_view_other_commercial_activities(): void
    {
        $userA = $this->userForRole('commercial');
        $userB = $this->userForRole('commercial');

        Sanctum::actingAs($userA);
        $this->postJson('/api/commercial-activities', [
            'type' => 'Visite',
            'date' => '2026-10-01',
            'time' => '11:00',
            'client_name' => 'Client A',
            'result' => 'RDV confirmé',
            'objective' => 'Visite',
            'commentary' => 'Activité A',
        ])->assertCreated();

        Sanctum::actingAs($userB);
        $this->postJson('/api/commercial-activities', [
            'type' => 'Rendez-vous',
            'date' => '2026-10-02',
            'time' => '15:00',
            'client_name' => 'Client B',
            'result' => 'Excellente discussion',
            'objective' => 'Rencontre',
            'commentary' => 'Activité B',
        ])->assertCreated();

        $response = $this->getJson('/api/commercial-activities');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($userB->id, $response->json('data.0.user_id'));
    }

    public function test_directrice_can_view_all_activities(): void
    {
        $commercialA = $this->userForRole('commercial');
        $commercialB = $this->userForRole('commercial');
        $directrice = $this->userForRole('directrice');

        Sanctum::actingAs($commercialA);
        $this->postJson('/api/commercial-activities', [
            'type' => 'Appel',
            'date' => '2026-10-01',
            'time' => '08:00',
            'client_name' => 'Client A',
            'result' => 'Résultat A',
            'objective' => 'Prospection',
            'commentary' => 'Commentaire A',
        ]);

        Sanctum::actingAs($commercialB);
        $this->postJson('/api/commercial-activities', [
            'type' => 'Visite',
            'date' => '2026-10-02',
            'time' => '10:00',
            'client_name' => 'Client B',
            'result' => 'Résultat B',
            'objective' => 'Visite',
            'commentary' => 'Commentaire B',
        ]);

        Sanctum::actingAs($directrice);
        $response = $this->getJson('/api/commercial-activities');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_dashboard_stats_include_breakdown_by_type_and_commercial(): void
    {
        $commercialA = $this->userForRole('commercial');
        $commercialB = $this->userForRole('commercial');
        $directrice = $this->userForRole('directrice');

        Sanctum::actingAs($commercialA);
        $this->postJson('/api/commercial-activities', [
            'type' => 'Appel',
            'date' => '2026-10-01',
            'time' => '08:00',
            'client_name' => 'Client A',
            'result' => 'Résultat A',
            'objective' => 'Prospection',
            'commentary' => 'Commentaire A',
        ]);
        $this->postJson('/api/commercial-activities', [
            'type' => 'Visite',
            'date' => '2026-10-03',
            'time' => '10:00',
            'client_name' => 'Client C',
            'result' => 'RDV validé',
            'objective' => 'Visite',
            'commentary' => 'Commentaire C',
        ]);

        Sanctum::actingAs($commercialB);
        $this->postJson('/api/commercial-activities', [
            'type' => 'Rendez-vous',
            'date' => '2026-10-02',
            'time' => '15:00',
            'client_name' => 'Client B',
            'result' => 'Excellent échange',
            'objective' => 'Rencontre',
            'commentary' => 'Commentaire B',
        ]);

        Sanctum::actingAs($directrice);
        $response = $this->getJson('/api/commercial-activities/stats');

        $response->assertOk();
        $response->assertJsonPath('data.total_activities', 3);
        $this->assertArrayHasKey('by_type', $response->json('data'));
        $this->assertArrayHasKey('by_commercial', $response->json('data'));
        $this->assertSame(1, $response->json('data.by_type.Appel'));
        $this->assertSame(1, $response->json('data.by_type.Visite'));
        $this->assertSame(1, $response->json('data.by_type.Rendez-vous'));
        $this->assertCount(2, $response->json('data.by_commercial'));
        $this->assertSame(3, array_sum(array_column($response->json('data.by_commercial'), 'total')));
    }
}
