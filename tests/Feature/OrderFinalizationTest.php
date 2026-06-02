<?php

namespace Tests\Feature;

use App\Mail\PaymentConfirmed;
use App\Models\Client;
use App\Models\Order;
use App\Services\OrderFinalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderFinalizationTest extends TestCase
{
    use RefreshDatabase;

    /** Regresión: al confirmarse un pago se envía el correo de confirmación (comprobante). */
    public function test_finalize_sends_payment_confirmation_email(): void
    {
        Mail::fake();

        $client = Client::create([
            'legal_name' => 'Ivan', 'email' => 'ivan@example.com',
            'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true,
        ]);

        $order = Order::create([
            'client_id' => $client->id, 'series' => 'V', 'payment_form' => '04',
            'payment_method' => 'PUE', 'use_cfdi' => 'S01', 'status' => 'sent',
            'subtotal' => 10, 'iva_amount' => 1.6, 'total' => 11.6, 'paid_at' => now(),
        ]);

        $payment = $order->payments()->create([
            'gateway' => 'mercadopago', 'amount' => 11.6, 'currency' => 'MXN',
            'status' => 'approved', 'payment_type' => 'credit_card', 'paid_at' => now(),
        ]);

        (new OrderFinalizationService())->finalize($payment);

        Mail::assertSent(PaymentConfirmed::class);
    }

    /** Sin email del cliente no truena (no intenta enviar). */
    public function test_finalize_is_safe_without_client_email(): void
    {
        Mail::fake();

        $client = Client::create(['legal_name' => 'SinCorreo', 'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true]);
        $order = Order::create([
            'client_id' => $client->id, 'series' => 'V', 'payment_form' => '04', 'payment_method' => 'PUE',
            'use_cfdi' => 'S01', 'status' => 'sent', 'subtotal' => 10, 'iva_amount' => 0, 'total' => 10, 'paid_at' => now(),
        ]);
        $payment = $order->payments()->create(['gateway' => 'mercadopago', 'amount' => 10, 'currency' => 'MXN', 'status' => 'approved', 'payment_type' => 'credit_card', 'paid_at' => now()]);

        (new OrderFinalizationService())->finalize($payment);

        Mail::assertNothingSent();
    }
}
