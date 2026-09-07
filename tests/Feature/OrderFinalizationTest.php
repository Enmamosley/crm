<?php

namespace Tests\Feature;

use App\Mail\PaymentConfirmed;
use App\Models\Client;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderFinalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderFinalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake(); // red de seguridad: ninguna llamada externa debe escaparse
    }

    private function unpaidOrder(array $attributes = [], ?string $email = 'ivan@example.com'): Order
    {
        $client = Client::create([
            'legal_name' => 'Ivan', 'email' => $email,
            'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true,
        ]);

        return Order::create(array_merge([
            'client_id' => $client->id, 'series' => 'V', 'payment_form' => '04',
            'payment_method' => 'PUE', 'use_cfdi' => 'S01', 'status' => 'sent',
            'subtotal' => 10, 'iva_amount' => 1.6, 'total' => 11.6,
        ], $attributes));
    }

    private function approvedPayment(Order $order, array $attributes = []): Payment
    {
        return $order->payments()->create(array_merge([
            'gateway' => 'mercadopago', 'amount' => 11.6, 'currency' => 'MXN',
            'status' => 'approved', 'payment_type' => 'credit_card', 'paid_at' => now(),
        ], $attributes));
    }

    /** Regresión: al confirmarse un pago se envía el correo de confirmación (comprobante). */
    public function test_finalize_sends_payment_confirmation_email(): void
    {
        $payment = $this->approvedPayment($this->unpaidOrder());

        $this->assertTrue((new OrderFinalizationService())->finalize($payment));

        Mail::assertSent(PaymentConfirmed::class);
    }

    /** Sin email del cliente no truena (no intenta enviar). */
    public function test_finalize_is_safe_without_client_email(): void
    {
        $order   = $this->unpaidOrder(['iva_amount' => 0, 'total' => 10], email: null);
        $payment = $this->approvedPayment($order, ['amount' => 10]);

        (new OrderFinalizationService())->finalize($payment);

        Mail::assertNothingSent();
    }

    /** Marca pagada, consume el cupón y no repite nada en una segunda llamada. */
    public function test_finalize_marks_paid_consumes_discount_and_is_idempotent(): void
    {
        $code = DiscountCode::create([
            'code' => 'TEST20', 'type' => 'percentage', 'value' => 20,
            'max_uses' => 5, 'times_used' => 0, 'active' => true,
        ]);
        $order   = $this->unpaidOrder(['discount_code' => 'TEST20']);
        $payment = $this->approvedPayment($order);

        $service = new OrderFinalizationService();

        $this->assertTrue($service->finalize($payment));
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame('sent', $order->fresh()->status);
        $this->assertSame(1, $code->fresh()->times_used);

        // Segunda llamada: la orden ya está pagada, así que no hace nada.
        $this->assertFalse($service->finalize($payment));
        $this->assertSame(1, $code->fresh()->times_used);
        Mail::assertSent(PaymentConfirmed::class, 1);
        $this->assertSame(1, \App\Models\ActivityLog::where('action', 'payment_approved')->count());
    }

    /** Los pagos del panel marcan la orden como 'paid', no como 'sent'. */
    public function test_finalize_uses_given_status_for_manual_payments(): void
    {
        $order   = $this->unpaidOrder(['status' => 'draft']);
        $payment = $this->approvedPayment($order, ['payment_type' => 'manual', 'payment_method_id' => '03']);

        $this->assertTrue((new OrderFinalizationService())->finalize($payment, null, 'paid'));

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertTrue($order->fresh()->isPaid());
    }

    /**
     * '99' es "por definir": no debe pisar la forma de pago que el admin
     * eligió al registrar el pago manual.
     */
    public function test_finalize_never_clobbers_payment_form_with_99(): void
    {
        $order   = $this->unpaidOrder(['payment_form' => '03']);
        $payment = $this->approvedPayment($order, ['payment_type' => 'cash_on_delivery']);

        $this->assertSame('99', $payment->satPaymentForm());

        (new OrderFinalizationService())->finalize($payment, null, 'paid');

        $this->assertSame('03', $order->fresh()->payment_form);
    }

    /** El pago manual traduce a código SAT lo que el admin eligió en el panel. */
    public function test_manual_payment_maps_admin_choice_to_sat_code(): void
    {
        $order   = $this->unpaidOrder();
        $manual  = $this->approvedPayment($order, ['payment_type' => 'manual', 'payment_method_id' => '28']);
        $paypal  = $this->approvedPayment($order, ['payment_type' => 'paypal']);
        $garbage = $this->approvedPayment($order, ['payment_type' => 'manual', 'payment_method_id' => 'bank_transfer']);

        $this->assertSame('28', $manual->satPaymentForm());
        $this->assertSame('05', $paypal->satPaymentForm());
        $this->assertSame('99', $garbage->satPaymentForm());
    }
}
