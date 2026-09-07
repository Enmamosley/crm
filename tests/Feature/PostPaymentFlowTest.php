<?php

namespace Tests\Feature;

use App\Mail\PaymentConfirmed;
use App\Models\Client;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Los flujos que confirmaban un pago hacían cada uno su propia mitad del
 * trabajo posterior: las pasarelas timbraban y enviaban correo, los
 * controladores aprovisionaban y avisaban a Meta, y los pagos del panel no
 * hacían ni lo uno ni lo otro. Ahora todos pasan por OrderFinalizationService.
 */
class PostPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function unpaidOrder(array $attributes = []): Order
    {
        $client = Client::create([
            'legal_name' => 'Cliente Pago SA', 'name' => 'Cliente Pago',
            'email' => 'pago@test.com', 'tax_id' => 'CHA150312AB1', 'tax_system' => '601',
            'cfdi_use' => 'G03', 'address_zip' => '06600', 'portal_active' => true,
        ]);

        return Order::create(array_merge([
            'client_id' => $client->id, 'series' => 'F', 'folio_number' => 1,
            'payment_form' => '03', 'payment_method' => 'PUE', 'use_cfdi' => 'G03',
            'billing_preference' => 'fiscal', 'status' => 'sent',
            'subtotal' => 1000, 'iva_amount' => 160, 'total' => 1160,
        ], $attributes));
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('secret'), 'role' => 'admin',
        ]);
    }

    // ── Webhook de Mercado Pago ──────────────────────────

    /** El webhook aprueba el pago y desencadena correo y cupón una sola vez. */
    public function test_mercadopago_webhook_finalizes_order_once(): void
    {
        Setting::set('mp_webhook_secret', 's3cret');
        Setting::set('mp_access_token', 'TEST-token');
        Http::fake([
            'api.mercadopago.com/v1/payments/123' => Http::response([
                'id' => '123', 'status' => 'approved', 'status_detail' => 'accredited',
                'payment_type_id' => 'credit_card', 'date_approved' => now()->toIso8601String(),
            ]),
        ]);

        $code  = DiscountCode::create([
            'code' => 'WEB10', 'type' => 'percentage', 'value' => 10,
            'max_uses' => 5, 'times_used' => 0, 'active' => true,
        ]);
        $order = $this->unpaidOrder(['discount_code' => 'WEB10']);
        $order->payments()->create([
            'gateway' => 'mercadopago', 'mp_payment_id' => '123', 'amount' => 1160,
            'currency' => 'MXN', 'status' => 'pending', 'payment_type' => 'credit_card',
        ]);

        $this->postWebhook('req-1')->assertOk();

        $this->assertNotNull($order->fresh()->paid_at);
        Mail::assertSent(PaymentConfirmed::class, 1);
        $this->assertSame(1, $code->fresh()->times_used);

        // Reentrega del mismo evento con otro request-id: no debe duplicar nada.
        $this->postWebhook('req-2')->assertOk();

        Mail::assertSent(PaymentConfirmed::class, 1);
        $this->assertSame(1, $code->fresh()->times_used);
    }

    public function test_mercadopago_webhook_rejects_invalid_signature(): void
    {
        Setting::set('mp_webhook_secret', 's3cret');

        $this->withHeaders(['x-signature' => 'ts=1,v1=deadbeef', 'x-request-id' => 'req-1'])
            ->postJson('/api/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '123']])
            ->assertStatus(403);

        Mail::assertNothingSent();
    }

    private function postWebhook(string $requestId)
    {
        $ts = time();
        $v1 = hash_hmac('sha256', "id:123;request-id:{$requestId};ts:{$ts};", 's3cret');

        return $this->withHeaders([
            'x-signature'  => "ts={$ts},v1={$v1}",
            'x-request-id' => $requestId,
        ])->postJson('/api/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '123']]);
    }

    // ── Pagos registrados desde el panel ─────────────────

    /** Un pago manual ahora sí envía comprobante y marca la orden pagada. */
    public function test_manual_payment_sends_receipt_and_keeps_admin_payment_form(): void
    {
        $order = $this->unpaidOrder(['payment_form' => '99']);

        $this->actingAs($this->admin())
            ->post("/panel/orders/{$order->id}/pay-manual", [
                'amount'       => 1160,
                'payment_form' => '03',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('03', $order->payment_form);
        Mail::assertSent(PaymentConfirmed::class, 1);
    }

    public function test_manual_payment_is_rejected_on_an_already_paid_order(): void
    {
        $order = $this->unpaidOrder(['status' => 'paid', 'paid_at' => now()]);

        $this->actingAs($this->admin())
            ->post("/panel/orders/{$order->id}/pay-manual", ['amount' => 1160, 'payment_form' => '03'])
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    /** Confirmar una transferencia también envía comprobante. */
    public function test_approving_a_transfer_sends_receipt(): void
    {
        $order   = $this->unpaidOrder();
        $payment = $order->payments()->create([
            'gateway' => 'manual', 'amount' => 1160, 'currency' => 'MXN',
            'status' => 'pending', 'payment_type' => 'transfer',
            'payment_method_id' => 'bank_transfer',
        ]);

        $this->actingAs($this->admin())
            ->patch("/panel/payments/{$payment->id}/approve")
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame('approved', $payment->fresh()->status);
        Mail::assertSent(PaymentConfirmed::class, 1);
    }

    // ── Idempotencia de la transición ────────────────────

    /** markPaid() sólo puede ganar una vez, aunque dos flujos lleguen a la vez. */
    public function test_mark_paid_transitions_only_once(): void
    {
        $order = $this->unpaidOrder();

        $this->assertTrue($order->markPaid(now(), 'sent'));
        $this->assertFalse($order->fresh()->markPaid(now(), 'paid'));
        $this->assertSame('sent', $order->fresh()->status);
    }

    /** Una captura de PayPal reprocesada no vuelve a finalizar la orden. */
    public function test_paypal_capture_replay_does_not_duplicate_side_effects(): void
    {
        $order = $this->unpaidOrder();

        $capture = [
            'id' => 'CAP-1', 'status' => 'COMPLETED',
            'purchase_units' => [[
                'reference_id' => (string) $order->id,
                'payments' => ['captures' => [[
                    'id' => 'CAP-1', 'status' => 'COMPLETED',
                    'amount' => ['value' => '1160.00', 'currency_code' => 'MXN'],
                    'create_time' => now()->toIso8601String(),
                ]]],
            ]],
        ];

        $paypal = app(\App\Services\PayPalService::class);
        $paypal->processCapture($order, $capture);
        $paypal->processCapture($order->fresh(), $capture);

        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
        Mail::assertSent(PaymentConfirmed::class, 1);
    }
}
