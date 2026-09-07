<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DunningAttempt;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * El legado de client_invoices quedó retirado: las facturas viven en `orders`
 * desde 2026_03_25_100002 y las tres tablas hijas se referencian por order_id.
 */
class LegacySchemaTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $client = Client::create([
            'legal_name' => 'Legacy SA', 'name' => 'Legacy',
            'email' => 'legacy@test.com', 'tax_id' => 'CHA150312AB1', 'tax_system' => '601',
            'cfdi_use' => 'G03', 'address_zip' => '06600', 'portal_active' => true,
        ]);

        return Order::create([
            'client_id' => $client->id, 'series' => 'F', 'folio_number' => 1,
            'payment_form' => '03', 'payment_method' => 'PUE', 'use_cfdi' => 'G03',
            'billing_preference' => 'fiscal', 'status' => 'sent',
            'subtotal' => 1000, 'iva_amount' => 160, 'total' => 1160,
        ]);
    }

    public function test_legacy_table_and_columns_are_gone(): void
    {
        $this->assertFalse(Schema::hasTable('client_invoices'));

        foreach (['payments', 'invoice_items', 'dunning_attempts'] as $table) {
            $this->assertFalse(
                Schema::hasColumn($table, 'client_invoice_id'),
                "La tabla {$table} conserva la columna legacy client_invoice_id."
            );
        }
    }

    public function test_child_rows_are_created_through_order_id(): void
    {
        $order = $this->order();

        $attempt = DunningAttempt::create([
            'order_id'       => $order->id,
            'attempt_number' => 1,
            'status'         => 'pending',
            'scheduled_at'   => now(),
        ]);

        $item = $order->items()->create([
            'description' => 'Servicio de prueba',
            'quantity'    => 1,
            'unit_price'  => 1000,
            'total'       => 1000,
        ]);

        $payment = $order->payments()->create([
            'gateway'      => 'paypal',
            'amount'       => 1160,
            'currency'     => 'MXN',
            'status'       => 'approved',
            'payment_type' => 'paypal',
        ]);

        $this->assertTrue($attempt->order->is($order));
        $this->assertTrue($item->order->is($order));
        $this->assertTrue($payment->order->is($order));
    }
}
