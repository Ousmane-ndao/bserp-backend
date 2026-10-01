<?php

namespace Tests\Feature;

use App\Mail\InvoiceSentToClientMail;
use App\Models\Client;
use App\Models\Destination;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceAuditLog;
use App\Models\InvoiceDispatch;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceDeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    private function userForRole(string $frontendKey): User
    {
        $roleName = RoleMapper::toDbName($frontendKey);
        $role = Role::query()->firstOrCreate(['name' => $roleName]);
        $email = 'invdel_'.uniqid('', true).'@test.com';
        $employee = Employee::query()->create([
            'name' => 'Inv '.$frontendKey,
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

    private function employeeUserForRole(string $roleName, string $name, string $email): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName]);
        $employee = Employee::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'role_id' => $role->id, 'statut' => 'Actif']
        );

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => bcrypt('password'),
                'employee_id' => $employee->id,
            ]
        );

        return $user->fresh(['employee.role']);
    }

    private function makeInvoice(array $clientAttrs = []): Invoice
    {
        $destination = Destination::query()->create([
            'name' => 'Dest Inv '.uniqid(),
            'region' => 'Afrique',
            'type_compte' => 'SIMPLE',
        ]);

        $client = Client::query()->create(array_merge([
            'prenom' => 'Awa',
            'nom' => 'Diop',
            'email' => 'awa.'.uniqid('', true).'@client.test',
            'telephone' => '771234567',
            'destination_id' => $destination->id,
        ], $clientAttrs));

        return Invoice::query()->create([
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'statut' => Invoice::STATUT_PAYEE,
            'montant_ttc' => 150000,
            'currency' => 'XOF',
        ]);
    }

    public function test_preview_uses_the_invoice_client_contact(): void
    {
        $user = $this->userForRole('comptable');
        $invoice = $this->makeInvoice(['email' => 'fiche.client@example.com', 'telephone' => '778889900']);
        Sanctum::actingAs($user);

        $this->getJson('/api/invoices/'.$invoice->id.'/delivery')
            ->assertOk()
            ->assertJsonPath('data.clientId', (string) $invoice->client_id)
            ->assertJsonPath('data.email', 'fiche.client@example.com')
            ->assertJsonPath('data.whatsappId', '221778889900');
    }

    public function test_email_send_uses_client_fiche_and_records_history(): void
    {
        Mail::fake();
        $user = $this->userForRole('comptable');
        $invoice = $this->makeInvoice(['email' => 'ok.client@example.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/invoices/'.$invoice->id.'/delivery', ['mode' => 'email'])
            ->assertOk()
            ->assertJsonPath('data.results.0.ok', true)
            ->assertJsonPath('data.results.0.channel', 'email');

        Mail::assertSent(InvoiceSentToClientMail::class, function (InvoiceSentToClientMail $mail) use ($invoice) {
            return $mail->invoice->is($invoice)
                && $mail->hasTo('ok.client@example.com');
        });

        $this->assertDatabaseHas('invoice_dispatches', [
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'channel' => InvoiceDispatch::CHANNEL_EMAIL,
            'status' => InvoiceDispatch::STATUS_SENT,
            'recipient' => 'ok.client@example.com',
        ]);
    }

    public function test_email_send_fails_when_address_missing(): void
    {
        Mail::fake();
        $user = $this->userForRole('comptable');
        $invoice = $this->makeInvoice(['email' => 'pas-une-adresse']);
        Sanctum::actingAs($user);

        $this->postJson('/api/invoices/'.$invoice->id.'/delivery', ['mode' => 'email'])
            ->assertOk()
            ->assertJsonPath('data.results.0.ok', false);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('invoice_dispatches', [
            'invoice_id' => $invoice->id,
            'channel' => InvoiceDispatch::CHANNEL_EMAIL,
            'status' => InvoiceDispatch::STATUS_FAILED,
        ]);
    }

    public function test_whatsapp_fails_when_number_not_on_whatsapp(): void
    {
        config([
            'whatsapp.token' => 'test-token',
            'whatsapp.phone_number_id' => '123',
            'whatsapp.graph_version' => 'v21.0',
        ]);
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/messages')) {
                return Http::response([
                    'error' => ['code' => 131026, 'message' => 'Message undeliverable'],
                ], 400);
            }

            return Http::response(['id' => 'media-1'], 200);
        });

        $user = $this->userForRole('comptable');
        $invoice = $this->makeInvoice();
        Sanctum::actingAs($user);

        $this->postJson('/api/invoices/'.$invoice->id.'/delivery', ['mode' => 'whatsapp'])
            ->assertOk()
            ->assertJsonPath('data.results.0.ok', false)
            ->assertJsonFragment(['errorMessage' => 'le numéro n’est pas associé à WhatsApp']);

        $this->assertSame(InvoiceDispatch::STATUS_FAILED, InvoiceDispatch::query()->first()?->status);
    }

    public function test_both_channels_report_separately(): void
    {
        Mail::fake();
        config([
            'whatsapp.token' => 'test-token',
            'whatsapp.phone_number_id' => '123',
            'whatsapp.graph_version' => 'v21.0',
        ]);
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/messages')) {
                return Http::response(['messages' => [['id' => 'wamid.ok']]], 200);
            }

            return Http::response(['id' => 'media-1'], 200);
        });

        $user = $this->userForRole('comptable');
        $invoice = $this->makeInvoice(['email' => 'both@example.com', 'telephone' => '770000001']);
        Sanctum::actingAs($user);

        $this->postJson('/api/invoices/'.$invoice->id.'/delivery', ['mode' => 'both'])
            ->assertOk()
            ->assertJsonPath('data.results.0.ok', true)
            ->assertJsonPath('data.results.0.channel', 'email')
            ->assertJsonPath('data.results.1.ok', true)
            ->assertJsonPath('data.results.1.channel', 'whatsapp');

        $this->assertSame(2, InvoiceDispatch::query()->count());
    }

    public function test_request_cannot_override_recipient_email(): void
    {
        Mail::fake();
        $user = $this->userForRole('comptable');
        $invoice = $this->makeInvoice(['email' => 'vrai.client@example.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/invoices/'.$invoice->id.'/delivery', [
            'mode' => 'email',
            'email' => 'autre.personne@example.com',
        ])->assertOk();

        Mail::assertSent(InvoiceSentToClientMail::class, function (InvoiceSentToClientMail $mail) {
            return $mail->hasTo('vrai.client@example.com') && ! $mail->hasTo('autre.personne@example.com');
        });
    }

    public function test_log_mailer_is_not_recorded_as_sent(): void
    {
        $user = $this->userForRole('comptable');
        $invoice = $this->makeInvoice(['email' => 'jamais.recu@example.com']);
        Sanctum::actingAs($user);
        config(['mail.default' => 'log']);

        $this->postJson('/api/invoices/'.$invoice->id.'/delivery', ['mode' => 'email'])
            ->assertOk()
            ->assertJsonPath('data.results.0.ok', false)
            ->assertJsonPath('data.results.0.status', 'failed')
            ->assertJsonPath('data.results.0.channel', 'email');

        $this->assertDatabaseHas('invoice_dispatches', [
            'invoice_id' => $invoice->id,
            'channel' => InvoiceDispatch::CHANNEL_EMAIL,
            'status' => InvoiceDispatch::STATUS_FAILED,
            'recipient' => 'jamais.recu@example.com',
        ]);
    }

    public function test_both_reports_email_and_unconfigured_whatsapp_separately(): void
    {
        Mail::fake();
        config([
            'whatsapp.token' => '',
            'whatsapp.phone_number_id' => '',
        ]);

        $user = $this->userForRole('comptable');
        $invoice = $this->makeInvoice(['email' => 'mixte@example.com', 'telephone' => '773932069']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/invoices/'.$invoice->id.'/delivery', ['mode' => 'both'])
            ->assertOk()
            ->assertJsonPath('data.results.0.ok', true)
            ->assertJsonPath('data.results.0.channel', 'email')
            ->assertJsonPath('data.results.1.ok', false)
            ->assertJsonPath('data.results.1.channel', 'whatsapp');

        $this->assertStringContainsString('n’est pas configuré', (string) $response->json('data.results.1.errorMessage'));

        Mail::assertSent(InvoiceSentToClientMail::class, function (InvoiceSentToClientMail $mail) {
            return $mail->hasTo('mixte@example.com') && count($mail->attachments()) === 1;
        });
    }

    public function test_conseillere_pedagogique_can_create_invoice(): void
    {
        $user = $this->userForRole('conseillere_pedagogique');
        $destination = Destination::query()->create([
            'name' => 'Dest CP '.uniqid(),
            'region' => 'Afrique',
            'type_compte' => 'SIMPLE',
        ]);
        $client = Client::query()->create([
            'prenom' => 'Marie',
            'nom' => 'Sarr',
            'email' => 'marie.'.uniqid('', true).'@client.test',
            'telephone' => '770001122',
            'destination_id' => $destination->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/invoices', [
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'date_echeance' => now()->addDays(15)->toDateString(),
            'statut' => Invoice::STATUT_ENVOYEE,
            'montant_ttc' => 250000,
            'currency' => 'XOF',
            'notes' => 'Facture de test',
        ])->assertCreated();

        $this->assertDatabaseHas('invoices', [
            'client_id' => $client->id,
            'statut' => Invoice::STATUT_ENVOYEE,
            'montant_ttc' => '250000.00',
        ]);
    }

    public function test_invoice_is_tracked_with_creator_and_role(): void
    {
        $user = $this->userForRole('conseillere_pedagogique');
        $destination = Destination::query()->create([
            'name' => 'Dest Trace '.uniqid(),
            'region' => 'Afrique',
            'type_compte' => 'SIMPLE',
        ]);
        $client = Client::query()->create([
            'prenom' => 'Ndeye',
            'nom' => 'Fall',
            'email' => 'ndeye.'.uniqid('', true).'@client.test',
            'telephone' => '770011223',
            'destination_id' => $destination->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/invoices', [
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'date_echeance' => now()->addDays(10)->toDateString(),
            'statut' => Invoice::STATUT_ENVOYEE,
            'montant_ttc' => 100000,
            'currency' => 'XOF',
        ])->assertCreated();

        $invoice = Invoice::query()->latest('id')->first();

        $this->assertNotNull($invoice);
        $this->assertSame($user->id, $invoice->creator_user_id);
        $this->assertSame('Conseillère pédagogique', $invoice->creator_role);
    }

    public function test_invoice_creation_notifies_directrice_and_ndao(): void
    {
        Mail::fake();
        $creator = $this->userForRole('conseillere_pedagogique');
        $this->employeeUserForRole('Directrice', 'DG', 'madamebacci@gmail.com');
        $this->employeeUserForRole('Informaticien', 'M. Ndao', 'ousmanenda2004@gmail.com');

        $destination = Destination::query()->create([
            'name' => 'Dest Internal '.uniqid(),
            'region' => 'Afrique',
            'type_compte' => 'SIMPLE',
        ]);
        $client = Client::query()->create([
            'prenom' => 'Pape',
            'nom' => 'Mbaye',
            'email' => 'pape.'.uniqid('', true).'@client.test',
            'telephone' => '770555999',
            'destination_id' => $destination->id,
        ]);

        Sanctum::actingAs($creator);

        $response = $this->postJson('/api/invoices', [
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'date_echeance' => now()->addDays(12)->toDateString(),
            'statut' => Invoice::STATUT_ENVOYEE,
            'montant_ttc' => 200000,
            'currency' => 'XOF',
        ]);

        $response->assertCreated();

        $invoice = Invoice::query()->latest('id')->first();

        $this->assertNotNull($invoice);
        $this->assertDatabaseHas('invoice_dispatches', [
            'invoice_id' => $invoice->id,
            'channel' => 'internal',
            'status' => 'sent',
            'recipient' => 'madamebacci@gmail.com',
        ]);
        $this->assertDatabaseHas('invoice_dispatches', [
            'invoice_id' => $invoice->id,
            'channel' => 'internal',
            'status' => 'sent',
            'recipient' => 'ousmanenda2004@gmail.com',
        ]);

        Mail::assertSent(InvoiceSentToClientMail::class, function (InvoiceSentToClientMail $mail) use ($invoice) {
            return $mail->invoice->is($invoice)
                && $mail->hasTo('madamebacci@gmail.com')
                && str_contains($mail->bodyText, 'reçu PDF');
        });
        Mail::assertSent(InvoiceSentToClientMail::class, function (InvoiceSentToClientMail $mail) use ($invoice) {
            return $mail->invoice->is($invoice)
                && $mail->hasTo('ousmanenda2004@gmail.com')
                && str_contains($mail->bodyText, 'reçu PDF');
        });
    }

    public function test_accounting_roles_can_list_invoice_audit_logs(): void
    {
        $destination = Destination::query()->create([
            'name' => 'Dest Invoice Audit '.uniqid(),
            'region' => 'Afrique',
            'type_compte' => 'SIMPLE',
        ]);
        $client = Client::query()->create([
            'prenom' => 'Invoice',
            'nom' => 'Audit',
            'email' => 'invoice.audit.'.uniqid('', true).'@client.test',
            'telephone' => '770123456',
            'destination_id' => $destination->id,
        ]);
        $invoice = Invoice::query()->create([
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'statut' => Invoice::STATUT_ENVOYEE,
            'montant_ttc' => 150000,
            'currency' => 'XOF',
        ]);
        InvoiceAuditLog::query()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'user_id' => null,
            'action' => 'created',
            'payload' => [
                'before' => null,
                'after' => ['montant_ttc' => '150000.00'],
            ],
            'created_at' => now(),
        ]);

        $user = $this->userForRole('comptable');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/invoices/audit');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
        $this->assertSame('created', $response->json('data.0.action'));
        $this->assertSame((string) $invoice->id, (string) $response->json('data.0.invoice_id'));
    }

    public function test_commercial_cannot_access_invoice_audit_logs(): void
    {
        $invoice = $this->makeInvoice();
        InvoiceAuditLog::query()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'user_id' => null,
            'action' => 'created',
            'payload' => ['before' => null, 'after' => ['id' => $invoice->id]],
            'created_at' => now(),
        ]);

        $user = $this->userForRole('commercial');
        Sanctum::actingAs($user);

        $this->getJson('/api/invoices/audit')->assertForbidden();
    }
}
