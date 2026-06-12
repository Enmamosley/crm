<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\CfdiBuilderService;
use App\Services\InvoicingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinkokInvoicingTest extends TestCase
{
    use RefreshDatabase;

    /** Configura el emisor + CSD de prueba del SAT (EKU9003173C9) en el disco fake. */
    private function configureIssuer(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('csd/certificado.cer', file_get_contents(base_path('tests/fixtures/csd/EKU9003173C9.cer')));
        Storage::disk('local')->put('csd/llave.key', file_get_contents(base_path('tests/fixtures/csd/EKU9003173C9.key')));

        Setting::set('company_rfc', 'EKU9003173C9');
        Setting::set('company_legal_name', 'ESCUELA KEMPER URGATE');
        Setting::set('company_tax_system', '601');
        Setting::set('company_zip', '64000');
        Setting::set('csd_cer_path', 'csd/certificado.cer');
        Setting::set('csd_key_path', 'csd/llave.key');
        Setting::set('csd_key_password', '12345678a');
        Setting::set('iva_percentage', '16');
    }

    private function fiscalOrder(float $subtotal = 1000, float $iva = 160, float $total = 1160): Order
    {
        $client = Client::create([
            'legal_name' => 'Cliente Fiscal SA', 'name' => 'Cliente Fiscal',
            'email' => 'cf@test.com', 'tax_id' => 'CHA150312AB1', 'tax_system' => '601',
            'cfdi_use' => 'G03', 'address_zip' => '06600', 'portal_active' => true,
        ]);

        return Order::create([
            'client_id' => $client->id, 'series' => 'F', 'folio_number' => 7,
            'payment_form' => '03', 'payment_method' => 'PUE', 'use_cfdi' => 'G03',
            'billing_preference' => 'fiscal', 'status' => 'sent', 'paid_at' => now(),
            'subtotal' => $subtotal, 'iva_amount' => $iva, 'total' => $total,
            'notes' => 'Compra directa: Servicio de Prueba',
        ]);
    }

    /** El builder produce un CFDI 4.0 sellado y coherente con la orden. */
    public function test_builder_produces_sealed_cfdi(): void
    {
        $this->configureIssuer();
        $order = $this->fiscalOrder();

        try {
            $xml = (new CfdiBuilderService())->buildSealedXml($order);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'download') || str_contains($e->getMessage(), 'resolve')) {
                $this->markTestSkipped('Recursos XSLT del SAT no disponibles (sin internet): ' . $e->getMessage());
            }
            throw $e;
        }

        $this->assertMatchesRegularExpression('/ Sello="[^"]{100,}"/', $xml, 'El CFDI debe ir sellado');
        $this->assertStringContainsString('Rfc="EKU9003173C9"', $xml);
        $this->assertStringContainsString('Rfc="CHA150312AB1"', $xml);
        $this->assertStringContainsString('Total="1160.00"', $xml);
        $this->assertStringContainsString('TasaOCuota="0.160000"', $xml);
        $this->assertStringContainsString('Version="4.0"', $xml);
        $this->assertStringContainsString('LugarExpedicion="64000"', $xml);
    }

    /** Público en general: receptor genérico + InformacionGlobal obligatoria. */
    public function test_builder_publico_general(): void
    {
        $this->configureIssuer();
        $order = $this->fiscalOrder();
        $order->update(['billing_preference' => 'publico_general']);

        try {
            $xml = (new CfdiBuilderService())->buildSealedXml($order->fresh());
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'download') || str_contains($e->getMessage(), 'resolve')) {
                $this->markTestSkipped('Recursos XSLT del SAT no disponibles: ' . $e->getMessage());
            }
            throw $e;
        }

        $this->assertStringContainsString('Rfc="XAXX010101000"', $xml);
        $this->assertStringContainsString('InformacionGlobal', $xml);
        $this->assertStringContainsString('UsoCFDI="S01"', $xml);
    }

    /** Nunca timbrar un total distinto al cobrado: mismatch → excepción. */
    public function test_builder_rejects_total_mismatch(): void
    {
        $this->configureIssuer();
        // total declarado 9999 pero el concepto (subtotal 1000 + IVA) suma 1160
        $order = $this->fiscalOrder(1000, 160, 9999);

        $this->expectException(\RuntimeException::class);

        try {
            (new CfdiBuilderService())->buildSealedXml($order);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'download') || str_contains($e->getMessage(), 'resolve')) {
                $this->markTestSkipped('Recursos XSLT del SAT no disponibles');
            }
            $this->assertStringContainsString('no coincide', $e->getMessage());
            throw $e;
        }
    }

    /** Sin CP fiscal del cliente no se puede timbrar (regla CFDI 4.0). */
    public function test_builder_requires_client_zip(): void
    {
        $this->configureIssuer();
        $order = $this->fiscalOrder();
        $order->client->update(['address_zip' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('C.P. fiscal');

        (new CfdiBuilderService())->buildSealedXml($order->fresh()->load('client'));
    }

    /** El manager enruta por proveedor y la cancelación por el origen del documento. */
    public function test_invoicing_manager_routing(): void
    {
        Setting::set('invoicing_provider', 'finkok');
        $this->assertSame('finkok', (new InvoicingManager())->provider());
        // Sin credenciales/CSD → no configurado
        $this->assertFalse((new InvoicingManager())->isConfigured());

        Setting::set('invoicing_provider', 'facturapi');
        Setting::set('facturapi_api_key', 'sk_test_x');
        $this->assertTrue((new InvoicingManager())->isConfigured());
    }

    /** Un CFDI de Finkok se descarga desde archivos locales (portal y panel). */
    public function test_finkok_documents_download_locally(): void
    {
        Storage::fake('local');
        $order = $this->fiscalOrder();

        Storage::disk('local')->put('cfdi/finkok/UUID-TEST.xml', '<xml>cfdi</xml>');
        Storage::disk('local')->put('cfdi/finkok/UUID-TEST.pdf', '%PDF-1.7 fake');

        $order->fiscalDocument()->create([
            'source' => 'finkok', 'uuid' => 'UUID-TEST', 'status' => 'valid',
            'xml_path' => 'cfdi/finkok/UUID-TEST.xml', 'pdf_path' => 'cfdi/finkok/UUID-TEST.pdf',
            'stamped_at' => now(),
        ]);

        $token = $order->client->portal_token;
        $this->get("/portal/{$token}/orders/{$order->id}/xml")->assertStatus(200);
        $this->get("/portal/{$token}/orders/{$order->id}/pdf")->assertStatus(200);

        $admin = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => bcrypt('x'), 'role' => 'admin']);
        $this->actingAs($admin)->get("/panel/orders/{$order->id}/xml")->assertStatus(200);
        $this->actingAs($admin)->get("/panel/orders/{$order->id}/pdf")->assertStatus(200);
    }
}
