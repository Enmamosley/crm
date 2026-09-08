<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Las órdenes del carrito y de la compra directa no guardaban sus ítems: lo
 * comprado se codificaba en las notas ("Carrito: 1x A, 2x B") y el CFDI
 * reconstruía de ahí un concepto único. Con eso, un carrito que mezcla un
 * servicio exento con uno gravado no se podía timbrar —el concepto salía
 * entero gravado y el total no cuadraba con lo cobrado— y la factura no
 * detallaba lo que el cliente compró.
 */
class SaleItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('mp_access_token', 'TEST-token');
        Http::fake([
            '*api.mercadopago.com*' => Http::response([
                'id' => 111, 'status' => 'pending', 'status_detail' => 'pending_waiting_payment',
                'transaction_amount' => 1660, 'currency_id' => 'MXN',
            ]),
            '*' => Http::response(['id' => 'inv_1', 'uuid' => 'UUID-1', 'status' => 'valid']),
        ]);
    }

    private function service(string $name, float $price, bool $exempt = false): Service
    {
        $category = ServiceCategory::firstOrCreate(['name' => 'Hosting'], ['slug' => 'hosting']);

        return Service::create([
            'name' => $name, 'slug' => str($name)->slug(), 'price' => $price,
            'service_category_id' => $category->id, 'public' => true, 'active' => true,
            'iva_exempt' => $exempt, 'sat_product_key' => '81112100', 'sat_unit_key' => 'E48',
        ]);
    }

    /** Compra por carrito pagada con OXXO: no necesita tarjeta. */
    private function buyCart(array $services): Order
    {
        $this->startSession();
        foreach ($services as $service) {
            CartItem::create([
                'session_id' => session()->getId(), 'service_id' => $service->id, 'quantity' => 1,
            ]);
        }

        $this->app->call([app(\App\Http\Controllers\CartController::class), 'payWithOxxo'], [
            'request' => \Illuminate\Http\Request::create('/buy/cart/pay/oxxo', 'POST', [
                'name' => 'Cliente', 'email' => 'cliente@test.com',
            ]),
        ]);

        return Order::latest('id')->firstOrFail();
    }

    private function buyDirect(Service $service): Order
    {
        $this->post("/buy/{$service->slug}/pay/transfer", [
            'name' => 'Cliente', 'email' => 'cliente@test.com',
        ])->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    // ── La orden guarda lo que se vendió ─────────────────

    public function test_a_cart_order_keeps_one_item_per_service(): void
    {
        $order = $this->buyCart([
            $this->service('Hosting', 1000),
            $this->service('Colegiatura', 500, exempt: true),
        ]);

        $this->assertCount(2, $order->items);
        $this->assertSame(
            ['Colegiatura', 'Hosting'],
            $order->items->pluck('description')->sort()->values()->all(),
        );

        $exempt = $order->items->firstWhere('description', 'Colegiatura');
        $this->assertTrue($exempt->iva_exempt);
        $this->assertSame('500.00', $exempt->unit_price);
        $this->assertSame('81112100', $exempt->sat_product_key);
    }

    public function test_a_direct_purchase_keeps_its_item(): void
    {
        $order = $this->buyDirect($this->service('Hosting', 1000));

        $this->assertCount(1, $order->items);
        $this->assertSame('Hosting', $order->items->first()->description);
        $this->assertSame('1000.00', $order->items->first()->unit_price);
    }

    /**
     * Un borrador sin pagos se reutiliza (por ejemplo, tras abandonar un
     * checkout de PayPal). Al reutilizarlo, los ítems se reemplazan: no se
     * acumulan uno encima de otro.
     */
    public function test_reusing_a_draft_replaces_its_items(): void
    {
        $service = $this->service('Hosting', 1000);

        $first = $this->buyDirect($service);
        $first->payments()->delete(); // deja el borrador libre, como un checkout abandonado

        $order = $this->buyDirect($service);

        $this->assertSame($first->id, $order->id);
        $this->assertSame(1, Order::count());
        $this->assertCount(1, $order->items);
    }

    // ── Lo que esto desbloquea ───────────────────────────

    /**
     * El caso que antes no se podía timbrar: el CFDI sale con un concepto por
     * servicio y el exento va sin IVA, así que el total cuadra con lo cobrado.
     */
    public function test_a_mixed_cart_can_now_be_stamped(): void
    {
        Setting::set('facturapi_api_key', 'sk_test_123');

        $order = $this->buyCart([
            $this->service('Hosting', 1000),
            $this->service('Colegiatura', 500, exempt: true),
        ]);
        $order->update(['billing_preference' => 'publico_general']);
        $order->client->update(['facturapi_customer_id' => 'cus_1']);

        app(\App\Services\FacturapiService::class)->stampInvoice($order->fresh());

        Http::assertSent(function ($request) {
            if (!str_ends_with($request->url(), '/invoices')) {
                return true;
            }

            $items = collect($request['items']);
            $exempt = $items->firstWhere('product.description', 'Colegiatura');

            return $items->count() === 2
                && $exempt['product']['taxes'][0]['factor'] === 'Exento';
        });
    }

    // ── Descuentos ───────────────────────────────────────

    /**
     * Un descuento global se reparte a prorrata entre las líneas y viaja como
     * `Descuento` del concepto, que es como lo entiende el SAT.
     */
    public function test_a_discount_is_split_across_the_lines(): void
    {
        $order = Order::createWithFolio([
            'client_id' => \App\Models\Client::create([
                'legal_name' => 'ACME', 'email' => 'acme@test.com', 'tax_system' => '601', 'cfdi_use' => 'G03',
            ])->id,
            'series' => 'V', 'payment_form' => '03', 'payment_method' => 'PUE', 'use_cfdi' => 'G03',
            'status' => 'draft', 'subtotal' => 1350, 'iva_amount' => 144, 'total' => 1494,
        ]);

        $order->recordSaleItems([
            ['service' => $this->service('Hosting', 1000), 'quantity' => 1],
            ['service' => $this->service('Colegiatura', 500, exempt: true), 'quantity' => 1],
        ], discount: 150);

        $items = $order->fresh()->items->keyBy('description');
        $this->assertSame('100.00', $items['Hosting']->discount);
        $this->assertSame('50.00', $items['Colegiatura']->discount);

        // 900 de base gravada al 16% = 144, y el total cuadra con lo cobrado.
        $order->fresh()->assertChargedTotalMatches(1494.0);
    }

    /** Los céntimos del reparto no se pierden: la última línea absorbe el resto. */
    public function test_the_rounding_remainder_stays_in_the_last_line(): void
    {
        $order = Order::createWithFolio([
            'client_id' => \App\Models\Client::create([
                'legal_name' => 'ACME', 'email' => 'acme2@test.com', 'tax_system' => '601', 'cfdi_use' => 'G03',
            ])->id,
            'series' => 'V', 'payment_form' => '03', 'payment_method' => 'PUE', 'use_cfdi' => 'G03',
            'status' => 'draft', 'subtotal' => 0, 'iva_amount' => 0, 'total' => 0,
        ]);

        $order->recordSaleItems([
            ['service' => $this->service('A', 100), 'quantity' => 1],
            ['service' => $this->service('B', 100), 'quantity' => 1],
            ['service' => $this->service('C', 100), 'quantity' => 1],
        ], discount: 10);

        $this->assertSame(10.0, (float) $order->fresh()->items->sum('discount'));
    }

    /**
     * El CFDI que arma Finkok declara el descuento donde el SAT lo espera: en
     * cada concepto, con la base del impuesto ya rebajada. Comprobado además
     * contra los XSD oficiales (cfdv40).
     */
    public function test_the_stamped_cfdi_declares_the_discount_per_concept(): void
    {
        Setting::set('company_rfc', 'EKU9003173C9');
        Setting::set('company_legal_name', 'ESCUELA KEMPER URGATE');
        Setting::set('company_tax_system', '601');
        Setting::set('company_zip', '26015');

        $client = \App\Models\Client::create([
            'legal_name' => 'ACME SA', 'email' => 'acme3@test.com', 'tax_id' => 'CHA150312AB1',
            'tax_system' => '601', 'cfdi_use' => 'G03', 'address_zip' => '06600',
        ]);

        // 1500 de carrito con 150 de cupón: base gravada 900 → IVA 144 → total 1494.
        $order = Order::createWithFolio([
            'client_id' => $client->id, 'series' => 'V', 'payment_form' => '04',
            'payment_method' => 'PUE', 'use_cfdi' => 'G03', 'billing_preference' => 'fiscal',
            'status' => 'draft', 'subtotal' => 1350, 'iva_amount' => 144, 'total' => 1494,
        ]);
        $order->recordSaleItems([
            ['service' => $this->service('Hosting', 1000), 'quantity' => 1],
            ['service' => $this->service('Colegiatura', 500, exempt: true), 'quantity' => 1],
        ], discount: 150);

        $xml = app(\App\Services\CfdiBuilderService::class)->invoiceCreator($order->fresh())->asXml();

        $this->assertStringContainsString('SubTotal="1500.00"', $xml);
        $this->assertStringContainsString('Descuento="150.00"', $xml);
        $this->assertStringContainsString(' Total="1494.00"', $xml);

        // Un concepto por servicio, cada uno con su parte del descuento.
        $this->assertStringContainsString('Descripcion="Hosting" ValorUnitario="1000.00" Importe="1000.00" Descuento="100.00"', $xml);
        $this->assertStringContainsString('Descripcion="Colegiatura" ValorUnitario="500.00" Importe="500.00" Descuento="50.00"', $xml);

        // El IVA se calcula sobre la base ya rebajada, y el exento no lo causa.
        $this->assertStringContainsString('Base="900.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="144.00"', $xml);
        $this->assertStringContainsString('Base="450.00" Impuesto="002" TipoFactor="Exento"', $xml);
    }

    /** La ficha de la orden enseña ahora lo comprado, no sólo el resumen. */
    public function test_the_panel_lists_what_was_bought(): void
    {
        $admin = \App\Models\User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('secret'), 'role' => 'admin',
        ]);

        $order = $this->buyCart([
            $this->service('Hosting', 1000),
            $this->service('Colegiatura', 500, exempt: true),
        ]);

        $this->actingAs($admin)->get("/panel/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Conceptos')
            ->assertSee('Hosting')
            ->assertSee('Colegiatura')
            ->assertSee('(exento de IVA)');
    }
}
