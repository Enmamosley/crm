<?php

namespace Tests\Feature;

use App\Http\Controllers\CartController;
use App\Models\CartItem;
use App\Models\Client;
use App\Models\DiscountCode;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Un servicio marcado como exento de IVA (o no objeto del impuesto) se cobraba
 * con IVA: cada punto de venta calculaba `subtotal * 16%` sobre el total, sin
 * mirar la marca. Al timbrar, en cambio, el concepto salía exento — así que el
 * CFDI declaraba menos de lo cobrado. Con Finkok ni siquiera se podía timbrar:
 * el guard de coherencia de CfdiBuilderService abortaba.
 */
class ExemptTaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Cuerpo con la forma que espera FacturapiService; ninguna venta de
        // aquí sale a la red, el fake sólo evita que se escape una llamada.
        Http::fake(['*' => Http::response(['id' => 'inv_1', 'status' => 'valid'])]);
    }

    private function service(string $name, float $price, bool $exempt = false, string $taxObject = '02'): Service
    {
        $category = ServiceCategory::firstOrCreate(['name' => 'Hosting'], ['slug' => 'hosting']);

        return Service::create([
            'name' => $name, 'slug' => str($name)->slug(), 'price' => $price,
            'service_category_id' => $category->id, 'public' => true, 'active' => true,
            'iva_exempt' => $exempt, 'tax_object' => $taxObject,
        ]);
    }

    /** Compra directa por transferencia: no toca ninguna pasarela. */
    private function buyByTransfer(Service $service): Order
    {
        $this->post("/buy/{$service->slug}/pay/transfer", [
            'name' => 'Cliente', 'email' => 'cliente@test.com',
        ])->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    /**
     * Llena el carrito de la sesión en curso. El carrito va por `session_id` y
     * el harness de pruebas no arrastra la cookie de sesión entre peticiones,
     * así que se siembra y se lee dentro de la misma sesión.
     *
     * @param  array<string, float>  $prices  nombre => precio; el segundo es exento
     */
    private function fillCart(array $prices): void
    {
        $this->startSession();
        $exempt = false;
        foreach ($prices as $name => $price) {
            CartItem::create([
                'session_id' => session()->getId(),
                'service_id' => $this->service($name, $price, exempt: $exempt)->id,
                'quantity'   => 1,
            ]);
            $exempt = true;
        }
    }

    /** @return array<string, float> */
    private function cartTotals(): array
    {
        $data = $this->app->call([app(CartController::class), 'index'])->getData();

        return array_map(fn ($v) => (float) $v, [
            'subtotal' => $data['subtotal'],
            'discount' => $data['discount'],
            'iva'      => $data['iva'],
            'total'    => $data['total'],
        ]);
    }

    // ── Punto de venta ───────────────────────────────────

    public function test_an_exempt_service_is_not_charged_iva(): void
    {
        $order = $this->buyByTransfer($this->service('Colegiatura', 1000, exempt: true));

        $this->assertSame('1000.00', $order->subtotal);
        $this->assertSame('0.00', $order->iva_amount);
        $this->assertSame('1000.00', $order->total);
    }

    /** Regresión: lo que sí causa IVA se sigue cobrando igual. */
    public function test_a_taxable_service_keeps_charging_iva(): void
    {
        $order = $this->buyByTransfer($this->service('Hosting', 1000));

        $this->assertSame('160.00', $order->iva_amount);
        $this->assertSame('1160.00', $order->total);
    }

    /** '01' = no objeto del impuesto: tampoco causa IVA. */
    public function test_a_service_outside_the_tax_is_not_charged_either(): void
    {
        $order = $this->buyByTransfer($this->service('Cuota', 500, taxObject: '01'));

        $this->assertSame('0.00', $order->iva_amount);
        $this->assertSame('500.00', $order->total);
    }

    // ── Carrito ──────────────────────────────────────────

    public function test_a_mixed_basket_charges_iva_only_on_the_taxable_lines(): void
    {
        $this->fillCart(['Hosting' => 1000, 'Colegiatura' => 500]);

        $cart = $this->cartTotals();

        $this->assertSame(1500.0, $cart['subtotal']);
        $this->assertSame(160.0, $cart['iva']);
        $this->assertSame(1660.0, $cart['total']);
    }

    /** El descuento se reparte a prorrata: sólo la parte gravada baja el IVA. */
    public function test_a_discount_is_prorated_between_exempt_and_taxable_lines(): void
    {
        $this->fillCart(['Hosting' => 1000, 'Colegiatura' => 1000]);

        DiscountCode::create([
            'code' => 'MITAD', 'type' => 'percentage', 'value' => 50,
            'active' => true, 'used_count' => 0,
        ]);
        session(['discount_code' => 'MITAD']);

        $cart = $this->cartTotals();

        // 1000 gravados - 500 de descuento a prorrata = 500 de base; 16% = 80.
        $this->assertSame(1000.0, $cart['discount']);
        $this->assertSame(80.0, $cart['iva']);
        $this->assertSame(1080.0, $cart['total']);
    }

    // ── Cotizaciones ─────────────────────────────────────

    public function test_a_quote_with_an_exempt_service_totals_without_iva(): void
    {
        $lead = Lead::create(['name' => 'Prospecto', 'email' => 'p@test.com', 'status' => 'nuevo']);
        $quote = Quote::createWithNumber([
            'lead_id' => $lead->id, 'iva_percentage' => 16, 'subtotal' => 0,
            'iva_amount' => 0, 'total' => 0, 'status' => 'borrador',
            'valid_until' => now()->addDays(30),
        ]);

        $exempt  = $this->service('Colegiatura', 1000, exempt: true);
        $taxable = $this->service('Hosting', 1000);
        foreach ([$exempt, $taxable] as $service) {
            $quote->items()->create([
                'service_id' => $service->id, 'description' => $service->name,
                'quantity' => 1, 'unit_price' => $service->price, 'total' => $service->price,
            ]);
        }

        $quote->recalculate();

        $this->assertSame('160.00', $quote->fresh()->iva_amount);
        $this->assertSame('2160.00', $quote->fresh()->total);
    }

    // ── Orden manual del panel ───────────────────────────

    public function test_a_manual_order_honours_the_exempt_flag_of_its_items(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('secret'), 'role' => 'admin',
        ]);
        $client = Client::create([
            'legal_name' => 'ACME', 'email' => 'acme@test.com',
            'tax_system' => '601', 'cfdi_use' => 'G03',
        ]);

        $this->actingAs($admin)->post('/panel/orders', [
            'client_id' => $client->id, 'series' => 'F', 'payment_form' => '03',
            'payment_method' => 'PUE', 'use_cfdi' => 'G03',
            'items' => [
                ['description' => 'Colegiatura', 'quantity' => 1, 'unit_price' => 1000, 'iva_exempt' => 1],
                ['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ])->assertRedirect();

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('160.00', $order->iva_amount);
        $this->assertSame('2160.00', $order->total);
    }

    // ── Coherencia con el CFDI ───────────────────────────

    /**
     * Lo cobrado y lo timbrado tienen que cuadrar. Con Facturapi el concepto
     * exento viaja sin impuesto: si la orden cobró IVA, el CFDI salía por menos.
     */
    public function test_the_facturapi_payload_adds_up_to_the_charged_total(): void
    {
        Setting::set('invoicing_provider', 'facturapi');
        Setting::set('facturapi_api_key', 'sk_test_123');
        Http::fake(['*facturapi.io/*' => Http::response(['id' => 'inv_1', 'status' => 'valid'], 200)]);

        $order = $this->buyByTransfer($this->service('Colegiatura', 1000, exempt: true));
        $order->update(['billing_preference' => 'publico_general']);
        // Ya sincronizado: este test mira el payload de la factura, no el alta del cliente.
        $order->client->update(['facturapi_customer_id' => 'cus_1']);
        $order->refresh();

        app(\App\Services\FacturapiService::class)->stampInvoice($order);

        Http::assertSent(function ($request) use ($order) {
            if (!str_contains($request->url(), '/invoices')) {
                return true;
            }
            $stamped = collect($request['items'])->sum(function ($item) {
                $price = $item['product']['price'] * $item['quantity'];
                $tax   = collect($item['product']['taxes'])->sum(fn ($t) => $price * $t['rate']);

                return $price + $tax;
            });

            return abs($stamped - (float) $order->total) < 0.01;
        });
    }

    /**
     * Una compra directa no guarda ítems de orden: el concepto sale de las
     * notas. Finkok ya lo hacía; Facturapi enviaba la factura sin conceptos.
     */
    public function test_a_direct_purchase_is_stamped_with_its_concept(): void
    {
        Setting::set('facturapi_api_key', 'sk_test_123');

        $order = $this->buyByTransfer($this->service('Hosting', 1000));
        $order->client->update(['facturapi_customer_id' => 'cus_1']);

        app(\App\Services\FacturapiService::class)->stampInvoice($order->fresh());

        Http::assertSent(function ($request) {
            if (!str_ends_with($request->url(), '/invoices')) {
                return true;
            }

            return count($request['items'] ?? []) === 1
                && $request['items'][0]['product']['description'] === 'Hosting';
        });
    }

    /**
     * Cuando los conceptos no suman lo cobrado, no se timbra. Lo vigilaba sólo
     * Finkok; un carrito que mezcla exento y gravado se resume en un concepto
     * único y gravado, así que no cuadra y hay que pararlo en los dos PAC.
     */
    public function test_a_cfdi_that_does_not_add_up_is_refused(): void
    {
        Setting::set('facturapi_api_key', 'sk_test_123');
        $this->fillCart(['Hosting' => 1000, 'Colegiatura' => 500]);

        $client = Client::create([
            'legal_name' => 'ACME', 'email' => 'acme@test.com', 'tax_system' => '601',
            'cfdi_use' => 'G03', 'facturapi_customer_id' => 'cus_1',
        ]);
        $order = Order::createWithFolio([
            'client_id' => $client->id, 'series' => 'V', 'payment_form' => '03',
            'payment_method' => 'PUE', 'use_cfdi' => 'G03', 'status' => 'draft',
            'subtotal' => 1500, 'iva_amount' => 160, 'total' => 1660,
            'notes' => 'Carrito: 1x Hosting, 1x Colegiatura',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no coincide con el cobrado/');

        app(\App\Services\FacturapiService::class)->stampInvoice($order);
    }
}
