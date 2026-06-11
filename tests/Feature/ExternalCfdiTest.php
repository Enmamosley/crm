<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExternalCfdiTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = 'AAAA1111-BB22-CC33-DD44-EEEE55556666';

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('secret'), 'role' => 'admin',
        ]);
    }

    private function orderForClient(): Order
    {
        $client = Client::create([
            'legal_name' => 'Cliente Externo', 'email' => 'ext@test.com',
            'tax_system' => '601', 'cfdi_use' => 'G03', 'portal_active' => true,
        ]);

        return Order::create([
            'client_id' => $client->id, 'series' => 'F', 'folio_number' => 55,
            'payment_form' => '03', 'payment_method' => 'PUE', 'use_cfdi' => 'G03',
            'status' => 'sent', 'subtotal' => 1000, 'iva_amount' => 160, 'total' => 1160,
            'paid_at' => now(),
        ]);
    }

    private function fakeXml(): UploadedFile
    {
        $xml = '<?xml version="1.0"?><cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4">'
            . '<cfdi:Complemento><tfd:TimbreFiscalDigital UUID="' . self::UUID . '"/></cfdi:Complemento>'
            . '</cfdi:Comprobante>';

        return UploadedFile::fake()->createWithContent('factura.xml', $xml);
    }

    /** Adjuntar CFDI externo marca la orden como timbrada y extrae el UUID. */
    public function test_attach_external_cfdi_marks_order_stamped(): void
    {
        Storage::fake('local');
        $order = $this->orderForClient();

        $response = $this->actingAs($this->admin())->post("/panel/orders/{$order->id}/external-cfdi", [
            'xml' => $this->fakeXml(),
            'pdf' => UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));
        $order->refresh()->load('fiscalDocument');

        $this->assertTrue($order->isStamped());
        $this->assertTrue($order->fiscalDocument->isExternal());
        $this->assertSame(self::UUID, $order->fiscalDocument->uuid);
        Storage::disk('local')->assertExists($order->fiscalDocument->xml_path);
        Storage::disk('local')->assertExists($order->fiscalDocument->pdf_path);
    }

    /** No se puede adjuntar si la orden ya tiene CFDI activo. */
    public function test_cannot_attach_when_already_stamped(): void
    {
        Storage::fake('local');
        $order = $this->orderForClient();
        $order->fiscalDocument()->create(['source' => 'external', 'status' => 'valid', 'stamped_at' => now()]);

        $this->actingAs($this->admin())
            ->from(route('admin.orders.show', $order))
            ->post("/panel/orders/{$order->id}/external-cfdi", ['xml' => $this->fakeXml()])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame(1, $order->fiscalDocument()->count());
    }

    /** El cliente puede descargar el XML externo desde su portal; sin PDF adjunto da 404. */
    public function test_portal_serves_external_files(): void
    {
        Storage::fake('local');
        $order = $this->orderForClient();

        $this->actingAs($this->admin())->post("/panel/orders/{$order->id}/external-cfdi", [
            'xml' => $this->fakeXml(),
        ]);

        $token = $order->client->portal_token;

        $this->get("/portal/{$token}/orders/{$order->id}/xml")->assertStatus(200);
        $this->get("/portal/{$token}/orders/{$order->id}/pdf")->assertStatus(404);
    }

    /** Quitar un CFDI externo no llama al SAT y deja la orden sin timbrar. */
    public function test_removing_external_cfdi_detaches_it(): void
    {
        Storage::fake('local');
        $order = $this->orderForClient();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/panel/orders/{$order->id}/external-cfdi", ['xml' => $this->fakeXml()]);

        $this->actingAs($admin)
            ->delete("/panel/orders/{$order->id}/fiscal-document")
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertFalse($order->refresh()->load('fiscalDocument')->isStamped());
    }
}
