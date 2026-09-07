<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Prueba de humo de las 37 rutas del portal del cliente: cada una responde lo
 * que debe con los servicios externos sin configurar, y ninguna deja pasar
 * datos de otro cliente. Sirve de red para el troceo del controlador: los
 * nombres de ruta y las URIs no cambian.
 */
class PortalRoutesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private string $token;
    private Order $unpaidOrder;
    private Order $paidOrder;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::fake(); // ningún servicio está configurado, pero por si acaso

        $this->client = Client::create([
            'legal_name' => 'Portal SA', 'name' => 'Portal',
            'email' => 'portal@test.com', 'tax_id' => 'CHA150312AB1', 'tax_system' => '601',
            'cfdi_use' => 'G03', 'address_zip' => '06600', 'portal_active' => true,
        ]);
        $this->token = $this->client->portal_token;

        $this->unpaidOrder = $this->order(1, ['status' => 'sent']);
        $this->paidOrder = $this->order(2, ['status' => 'paid', 'paid_at' => now()]);
    }

    private function order(int $folio, array $attributes = [], ?Client $client = null): Order
    {
        return Order::create(array_merge([
            'client_id' => ($client ?? $this->client)->id, 'series' => 'F', 'folio_number' => $folio,
            'payment_form' => '03', 'payment_method' => 'PUE', 'use_cfdi' => 'G03',
            'billing_preference' => 'fiscal', 'subtotal' => 1000, 'iva_amount' => 160, 'total' => 1160,
        ], $attributes));
    }

    private function otherClient(): Client
    {
        return Client::create([
            'legal_name' => 'Ajeno SA', 'name' => 'Ajeno', 'email' => 'ajeno@test.com',
            'tax_id' => 'CHA150312AB1', 'tax_system' => '601', 'cfdi_use' => 'G03',
            'address_zip' => '06600', 'portal_active' => true,
        ]);
    }

    private function url(string $path = ''): string
    {
        return rtrim("/portal/{$this->token}/{$path}", '/');
    }

    // ── Vistas y descargas ───────────────────────────────

    public function test_dashboard_and_document_routes(): void
    {
        $this->get($this->url())->assertOk();

        $document = ClientDocument::create([
            'client_id' => $this->client->id, 'name' => 'contrato.pdf',
            'file_path' => 'docs/contrato.pdf', 'file_type' => 'application/pdf', 'file_size' => 10,
        ]);
        Storage::disk('local')->put('docs/contrato.pdf', 'contenido');

        $this->get($this->url("documents/{$document->id}"))->assertOk();
    }

    public function test_invoice_downloads_without_cfdi(): void
    {
        $this->get($this->url("orders/{$this->unpaidOrder->id}/pdf"))->assertNotFound();
        $this->get($this->url("orders/{$this->unpaidOrder->id}/xml"))->assertNotFound();

        // El recibo sólo existe para órdenes pagadas.
        $this->get($this->url("orders/{$this->paidOrder->id}/receipt"))->assertOk();
        $this->get($this->url("orders/{$this->unpaidOrder->id}/receipt"))->assertNotFound();
    }

    public function test_checkout_and_payment_status(): void
    {
        $this->get($this->url("orders/{$this->unpaidOrder->id}/checkout"))->assertOk();

        $payment = $this->unpaidOrder->payments()->create([
            'gateway' => 'mercadopago', 'amount' => 1160, 'currency' => 'MXN',
            'status' => 'pending', 'payment_type' => 'ticket',
        ]);
        $this->get($this->url("payments/{$payment->id}"))->assertOk();
    }

    public function test_quote_fiscal_and_ticket_views(): void
    {
        $lead = Lead::create(['name' => 'Lead Portal', 'email' => 'lead@test.com', 'status' => 'nuevo']);
        $this->client->update(['lead_id' => $lead->id]);
        $quote = Quote::create([
            'quote_number' => 'COT-001', 'lead_id' => $lead->id, 'subtotal' => 1000,
            'iva_percentage' => 16, 'iva_amount' => 160, 'total' => 1160,
            'status' => 'enviada', 'valid_until' => now()->addDays(15),
        ]);

        $this->get($this->url("quotes/{$quote->id}"))->assertOk();
        $this->get($this->url('fiscal'))->assertOk();
        $this->get($this->url('tickets'))->assertOk();
        $this->get($this->url('tickets/create'))->assertOk();

        $ticket = SupportTicket::create([
            'client_id' => $this->client->id, 'subject' => 'Ayuda',
            'description' => 'No carga', 'priority' => 'medium', 'status' => 'open',
        ]);
        $this->get($this->url("tickets/{$ticket->id}"))->assertOk();
    }

    /** Sin servicio de correo contratado, la gestión de buzones está cerrada. */
    public function test_mailboxes_require_email_service(): void
    {
        $this->get($this->url('mailboxes'))->assertForbidden();
        $this->post($this->url('mailboxes'), ['local' => 'hola', 'password' => 'secret123'])->assertForbidden();
    }

    /** Sin dominio Cosmotown ni paquete de hosting, estas rutas redirigen o rechazan. */
    public function test_domain_and_dns_routes_without_services(): void
    {
        $this->get($this->url('domain'))->assertRedirect();
        $this->get($this->url('domain/dns'))->assertStatus(422);
        $this->post($this->url('domain/dns'), ['records' => []])->assertStatus(422);
        $this->post($this->url('domain/nameservers'), ['nameservers' => ['ns1.test.com']])->assertStatus(422);

        $this->get($this->url('dns'))->assertRedirect();
        $this->post($this->url('dns'), ['type' => 'A', 'host' => '@', 'value' => '1.1.1.1'])->assertRedirect();
    }

    // ── Validación ───────────────────────────────────────

    public function test_payment_endpoints_validate_input(): void
    {
        $order = $this->unpaidOrder;

        $this->postJson($this->url("orders/{$order->id}/pay/card"), [])->assertStatus(422);
        $this->postJson($this->url("orders/{$order->id}/pay/oxxo"), [])->assertStatus(422);
        $this->postJson($this->url("orders/{$order->id}/pay/spei"), [])->assertStatus(422);
        $this->postJson($this->url("orders/{$order->id}/pay/paypal/create"), [])->assertStatus(422);
        $this->postJson($this->url("orders/{$order->id}/pay/paypal/capture"), [])->assertStatus(422);

        $this->post($this->url("orders/{$order->id}/pay/transfer"), [])
            ->assertSessionHasErrors(['email', 'billing_preference']);
    }

    public function test_fiscal_and_ticket_forms_validate_input(): void
    {
        $this->put($this->url('fiscal'), [])->assertSessionHasErrors();
        $this->post($this->url('tickets'), [])->assertSessionHasErrors();
    }

    // ── Seguridad ────────────────────────────────────────

    public function test_unknown_token_is_not_found(): void
    {
        $this->get('/portal/token-inexistente')->assertNotFound();
    }

    public function test_inactive_portal_is_not_found(): void
    {
        $this->client->update(['portal_active' => false]);

        $this->get($this->url())->assertNotFound();
    }

    public function test_resources_of_another_client_are_forbidden(): void
    {
        $other = $this->otherClient();
        $foreignOrder = $this->order(9, ['status' => 'sent'], $other);
        $foreignTicket = SupportTicket::create([
            'client_id' => $other->id, 'subject' => 'Ajeno',
            'description' => 'Ajeno', 'priority' => 'low', 'status' => 'open',
        ]);
        $foreignDocument = ClientDocument::create([
            'client_id' => $other->id, 'name' => 'ajeno.pdf',
            'file_path' => 'docs/ajeno.pdf', 'file_type' => 'application/pdf', 'file_size' => 10,
        ]);

        $this->get($this->url("orders/{$foreignOrder->id}/pdf"))->assertForbidden();
        $this->get($this->url("orders/{$foreignOrder->id}/checkout"))->assertForbidden();
        $this->get($this->url("tickets/{$foreignTicket->id}"))->assertForbidden();
        $this->get($this->url("documents/{$foreignDocument->id}"))->assertForbidden();
    }

    public function test_quote_of_another_lead_is_forbidden(): void
    {
        $otherLead = Lead::create(['name' => 'Otro', 'email' => 'otro@test.com', 'status' => 'nuevo']);
        $foreignQuote = Quote::create([
            'quote_number' => 'COT-999', 'lead_id' => $otherLead->id, 'subtotal' => 100,
            'iva_percentage' => 16, 'iva_amount' => 16, 'total' => 116,
            'status' => 'enviada', 'valid_until' => now()->addDays(15),
        ]);

        $this->get($this->url("quotes/{$foreignQuote->id}"))->assertForbidden();
    }

    public function test_foreign_payment_status_is_forbidden(): void
    {
        $other = $this->otherClient();
        $foreignOrder = $this->order(8, ['status' => 'sent'], $other);
        $foreignPayment = $foreignOrder->payments()->create([
            'gateway' => 'mercadopago', 'amount' => 1160, 'currency' => 'MXN',
            'status' => 'pending', 'payment_type' => 'ticket',
        ]);

        $this->get($this->url("payments/{$foreignPayment->id}"))->assertForbidden();
        $this->assertInstanceOf(Payment::class, $foreignPayment);
    }
}
