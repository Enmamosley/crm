<?php

namespace Tests\Feature;

use App\Mail\PaymentReminder;
use App\Models\Client;
use App\Models\DunningAttempt;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Los dos comandos de cobranza (invoices:send-reminders y dunning:process)
 * consultaban orders.stamped_at, una columna que no existe (vive en
 * fiscal_documents), y status='valid', un estado que las órdenes nunca tienen.
 * Ambos lanzaban excepción cada día al ejecutarse desde el scheduler.
 */
class PaymentRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function client(?string $email = 'cliente@test.com'): Client
    {
        return Client::create([
            'legal_name' => 'Cliente Cobranza SA', 'name' => 'Cliente Cobranza',
            'email' => $email, 'tax_id' => 'CHA150312AB1', 'tax_system' => '601',
            'cfdi_use' => 'G03', 'address_zip' => '06600', 'portal_active' => true,
        ]);
    }

    /** created_at no es fillable: se viaja en el tiempo para fijar la antigüedad. */
    private function orderCreatedDaysAgo(Client $client, int $days, array $attributes = []): Order
    {
        $this->travelTo(now()->subDays($days));

        $order = Order::createWithFolio(array_merge([
            'client_id' => $client->id, 'series' => 'F',
            'payment_form' => '03', 'payment_method' => 'PUE', 'use_cfdi' => 'G03',
            'billing_preference' => 'fiscal', 'status' => 'sent',
            'subtotal' => 1000, 'iva_amount' => 160, 'total' => 1160,
        ], $attributes));

        $this->travelBack();

        return $order;
    }

    public function test_reminder_sent_for_unpaid_sent_order_older_than_cutoff(): void
    {
        $order = $this->orderCreatedDaysAgo($this->client(), 10);

        $this->artisan('invoices:send-reminders', ['--days' => 7])->assertSuccessful();

        Mail::assertSent(PaymentReminder::class, fn ($mail) => $mail->invoice->is($order));
        $this->assertDatabaseHas('activity_logs', [
            'action'     => 'reminder_sent',
            'subject_id' => $order->id,
        ]);
    }

    public function test_no_reminder_for_paid_recent_draft_or_cancelled_orders(): void
    {
        $client = $this->client();
        $this->orderCreatedDaysAgo($client, 10, ['paid_at' => now()->subDays(9), 'status' => 'paid']);
        $this->orderCreatedDaysAgo($client, 2);                          // demasiado reciente
        $this->orderCreatedDaysAgo($client, 10, ['status' => 'draft']);  // checkout abandonado, sin CFDI
        $this->orderCreatedDaysAgo($client, 10, ['status' => 'cancelled']);

        $this->artisan('invoices:send-reminders', ['--days' => 7])->assertSuccessful();

        Mail::assertNothingSent();
    }

    /** Una recurrente auto-timbrada se queda en draft: manda su CFDI, no created_at. */
    public function test_draft_with_valid_cfdi_uses_stamped_at(): void
    {
        $client = $this->client();

        $old = $this->orderCreatedDaysAgo($client, 20, ['status' => 'draft']);
        $old->fiscalDocument()->create(['status' => 'valid', 'stamped_at' => now()->subDays(8)]);

        $recent = $this->orderCreatedDaysAgo($client, 20, ['status' => 'draft']);
        $recent->fiscalDocument()->create(['status' => 'valid', 'stamped_at' => now()->subDays(2)]);

        $this->artisan('invoices:send-reminders', ['--days' => 7])->assertSuccessful();

        Mail::assertSent(PaymentReminder::class, 1);
        Mail::assertSent(PaymentReminder::class, fn ($mail) => $mail->invoice->is($old));
    }

    /** El primer intento se registra una sola vez (falla sin la migración: la columna legacy era NOT NULL). */
    public function test_dunning_creates_first_attempt_once(): void
    {
        User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('secret'), 'role' => 'admin',
        ]);
        $order = $this->orderCreatedDaysAgo($this->client(), 4);

        $this->artisan('dunning:process')->assertSuccessful();
        $this->artisan('dunning:process')->assertSuccessful();

        $this->assertDatabaseHas('dunning_attempts', [
            'order_id'       => $order->id,
            'attempt_number' => 1,
            'status'         => 'sent',
        ]);
        $this->assertSame(1, DunningAttempt::where('order_id', $order->id)->count());
        Mail::assertSent(PaymentReminder::class, 1);
        $this->assertDatabaseHas('notifications', ['type' => 'dunning_sent']);
    }

    public function test_dunning_ignores_clients_without_email(): void
    {
        $order = $this->orderCreatedDaysAgo($this->client(email: null), 4);

        $this->artisan('dunning:process')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('dunning_attempts', [
            'order_id' => $order->id,
            'status'   => 'sent',
        ]);
    }
}
