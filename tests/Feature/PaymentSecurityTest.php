<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(float $total = 100.0): Order
    {
        $client = Client::create([
            'legal_name'    => 'Test SA',
            'email'         => 'test@example.com',
            'tax_system'    => '616',
            'cfdi_use'      => 'S01',
            'portal_active' => true,
        ]);

        return Order::create([
            'client_id'      => $client->id,
            'series'         => 'V',
            'payment_form'   => '05',
            'payment_method' => 'PUE',
            'use_cfdi'       => 'S01',
            'status'         => 'draft',
            'subtotal'       => $total,
            'iva_amount'     => 0,
            'total'          => $total,
        ]);
    }

    private function capture(Order $order, string $amount, ?string $reference = null): array
    {
        return [
            'id'     => 'PP-ORDER-1',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'reference_id' => $reference ?? (string) $order->id,
                'payments' => ['captures' => [[
                    'id'          => 'CAP-1',
                    'status'      => 'COMPLETED',
                    'amount'      => ['value' => $amount, 'currency_code' => 'MXN'],
                    'create_time' => '2026-06-01T00:00:00Z',
                ]]],
            ]],
        ];
    }

    /** Regresión P0: un Payment debe poder crearse sólo con order_id (client_invoice_id nullable). */
    public function test_payment_can_be_created_with_order_only(): void
    {
        $order = $this->makeOrder();

        $payment = $order->payments()->create([
            'gateway'      => 'paypal',
            'amount'       => 100,
            'currency'     => 'MXN',
            'status'       => 'approved',
            'payment_type' => 'paypal',
        ]);

        $this->assertNotNull($payment->id);
        $this->assertNull($payment->client_invoice_id);
        $this->assertTrue($payment->order->is($order));
    }

    /** P0: la captura NO marca pagado si el monto no coincide con el total. */
    public function test_paypal_capture_rejects_amount_mismatch(): void
    {
        $order = $this->makeOrder(100.0);

        try {
            (new PayPalService())->processCapture($order, $this->capture($order, '1.00'));
            $this->fail('Se esperaba una excepción por monto incorrecto.');
        } catch (\RuntimeException $e) {
            $this->assertNull($order->fresh()->paid_at);
            $this->assertSame(0, Payment::count());
        }
    }

    /** P0: la captura NO marca pagado si la referencia no corresponde a la orden. */
    public function test_paypal_capture_rejects_reference_mismatch(): void
    {
        $order = $this->makeOrder(100.0);

        $this->expectException(\RuntimeException::class);
        (new PayPalService())->processCapture($order, $this->capture($order, '100.00', '999999'));
    }

    /** Camino feliz: monto y referencia correctos → pago aprobado y orden pagada. */
    public function test_paypal_capture_approves_when_amount_and_reference_match(): void
    {
        $order = $this->makeOrder(100.0);

        $payment = (new PayPalService())->processCapture($order, $this->capture($order, '100.00'));

        $this->assertTrue($payment->isApproved());
        $this->assertNotNull($order->fresh()->paid_at);
    }

    /** P1: el cupón no se consume más allá de max_uses. */
    public function test_discount_consume_respects_max_uses(): void
    {
        $code = DiscountCode::create([
            'code' => 'TEST10', 'type' => 'percentage', 'value' => 10,
            'max_uses' => 1, 'times_used' => 1, 'active' => true,
        ]);

        DiscountCode::consumeForCode('TEST10');

        $this->assertSame(1, (int) $code->fresh()->times_used);
    }

    /** P1: el cupón sí se consume cuando hay margen. */
    public function test_discount_consume_increments_when_under_limit(): void
    {
        $code = DiscountCode::create([
            'code' => 'TEST20', 'type' => 'percentage', 'value' => 20,
            'max_uses' => 5, 'times_used' => 0, 'active' => true,
        ]);

        DiscountCode::consumeForCode('TEST20');

        $this->assertSame(1, (int) $code->fresh()->times_used);
    }
}
