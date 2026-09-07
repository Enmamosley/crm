<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\RecurringInvoiceSchedule;
use App\Models\Setting;
use App\Services\InvoicingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El timbrado de facturas recurrentes debe pasar SIEMPRE por InvoicingManager.
 * Antes llamaba a FacturapiService directamente y se activaba con la sola
 * presencia de facturapi_api_key, así que con invoicing_provider=finkok las
 * recurrentes no se timbraban (o se timbraban con el PAC equivocado).
 */
class RecurringInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private function dueSchedule(bool $autoStamp = true): RecurringInvoiceSchedule
    {
        $client = Client::create([
            'legal_name' => 'Recurrente SA', 'name' => 'Recurrente',
            'email' => 'rec@test.com', 'tax_id' => 'CHA150312AB1', 'tax_system' => '601',
            'cfdi_use' => 'G03', 'address_zip' => '06600', 'portal_active' => true,
        ]);

        // Ojo: recurring_invoice_schedules NO tiene columna billing_preference.
        $schedule = RecurringInvoiceSchedule::create([
            'client_id'       => $client->id,
            'series'          => 'R',
            'payment_form'    => '03',
            'payment_method'  => 'PUE',
            'use_cfdi'        => 'G03',
            'subtotal'        => 1000,
            'iva_amount'      => 160,
            'total'           => 1160,
            'frequency'       => 'monthly',
            'next_issue_date' => today(),
            'auto_stamp'      => $autoStamp,
            'active'          => true,
        ]);

        $schedule->items()->create([
            'description' => 'Hosting mensual',
            'quantity'    => 1,
            'unit_price'  => 1000,
            'total'       => 1000,
        ]);

        return $schedule;
    }

    /** Con proveedor finkok no debe tocarse Facturapi, aunque quede una API key vieja. */
    public function test_finkok_provider_never_calls_facturapi(): void
    {
        Http::fake();
        Setting::set('invoicing_provider', 'finkok');
        Setting::set('facturapi_api_key', 'sk_legacy'); // key residual: el código viejo timbraba por esto

        $schedule = $this->dueSchedule();

        $this->artisan('invoices:process-recurring')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(1, Order::count());
        $this->assertFalse(Order::first()->isStamped());
        $this->assertTrue($schedule->fresh()->next_issue_date->isAfter(today()));
    }

    /** Con proveedor facturapi sí timbra, y lo hace a través del manager. */
    public function test_facturapi_provider_stamps_through_manager(): void
    {
        Http::fake([
            'www.facturapi.io/v2/customers*' => Http::response(['id' => 'cus_1']),
            'www.facturapi.io/v2/invoices'   => Http::response([
                'id' => 'inv_1', 'status' => 'valid', 'uuid' => 'UUID-1',
            ]),
        ]);
        Setting::set('invoicing_provider', 'facturapi');
        Setting::set('facturapi_api_key', 'sk_test_x');

        $this->dueSchedule();

        $this->artisan('invoices:process-recurring')->assertSuccessful();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v2/invoices'));

        $order = Order::first();
        $this->assertTrue($order->fresh()->isStamped());
        $this->assertSame('inv_1', $order->fiscalDocument->facturapi_invoice_id);
    }

    /** Sin auto_stamp se crea la orden pero no se timbra. */
    public function test_auto_stamp_off_creates_order_without_stamping(): void
    {
        Http::fake();
        Setting::set('invoicing_provider', 'facturapi');
        Setting::set('facturapi_api_key', 'sk_test_x');

        $this->dueSchedule(autoStamp: false);

        $this->artisan('invoices:process-recurring')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(1, Order::count());
        $this->assertFalse(Order::first()->isStamped());
    }

    /** El comando resuelve el manager por el contenedor (sustituible en tests). */
    public function test_command_uses_container_bound_manager(): void
    {
        Http::fake();

        $fake = new class extends InvoicingManager
        {
            public int $calls = 0;

            public function __construct() {} // sin dependencias: es un doble

            public function isConfigured(): bool
            {
                return true;
            }

            public function stampInvoice(Order $order): array
            {
                $this->calls++;

                return ['success' => true, 'data' => []];
            }
        };
        $this->app->instance(InvoicingManager::class, $fake);

        $this->dueSchedule();

        $this->artisan('invoices:process-recurring')->assertSuccessful();

        $this->assertSame(1, $fake->calls);
        Http::assertNothingSent();
    }
}
