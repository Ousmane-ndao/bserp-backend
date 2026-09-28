<?php

namespace Tests\Feature;

use App\Mail\InvoiceSentToClientMail;
use App\Models\Client;
use App\Models\Destination;
use App\Models\Employee;
use App\Models\Invoice;
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
}
