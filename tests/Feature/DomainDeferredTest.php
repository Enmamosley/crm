<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\CosmotownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainDeferredTest extends TestCase
{
    use RefreshDatabase;

    private function hostingService(): Service
    {
        $cat = ServiceCategory::create(['name' => 'Hosting', 'slug' => 'hosting', 'active' => true]);

        return Service::create([
            'service_category_id' => $cat->id,
            'name'   => 'Hosting Pro', 'slug' => 'hosting-pro', 'price' => 1000,
            'public' => true, 'active' => true, 'requires_domain' => true,
        ]);
    }

    /** "Decidir después": el pago se acepta sin dominio y el cliente queda sin dominio asignado. */
    public function test_checkout_accepts_decide_later_without_domain(): void
    {
        $service = $this->hostingService();

        $response = $this->post("/buy/{$service->slug}/pay/transfer", [
            'name'        => 'Laura Campos',
            'email'       => 'laura@example.com',
            'domain'      => '',
            'domain_type' => 'later',
        ]);

        $response->assertRedirect();
        $client = Client::where('email', 'laura@example.com')->first();
        $this->assertNotNull($client);
        $this->assertNull($client->domain);
        $this->assertNull($client->domain_type);
        $this->assertSame(1, $client->invoices()->count());
    }

    /** Aunque venga un dominio tecleado, con 'later' no se guarda (cambió de pestaña). */
    public function test_decide_later_ignores_stray_domain_value(): void
    {
        $service = $this->hostingService();

        $this->post("/buy/{$service->slug}/pay/transfer", [
            'name'        => 'Laura Campos',
            'email'       => 'laura2@example.com',
            'domain'      => 'tecleado-pero-descartado.com',
            'domain_type' => 'later',
        ])->assertRedirect();

        $this->assertNull(Client::where('email', 'laura2@example.com')->value('domain'));
    }

    /** Los datos de registro (WHOIS) del checkout se guardan en el perfil del cliente. */
    public function test_registrant_data_is_saved_to_client_profile(): void
    {
        $service = $this->hostingService();

        $this->post("/buy/{$service->slug}/pay/transfer", [
            'name'        => 'Marco Polo',
            'email'       => 'marco@example.com',
            'domain'      => 'marcopolo.mx',
            'domain_type' => 'cosmotown',
            'reg_street'  => 'Av. Reforma 222',
            'reg_city'    => 'CDMX',
            'reg_state'   => 'Ciudad de México',
            'reg_zip'     => '06600',
            'reg_country' => 'mx',
        ])->assertRedirect();

        $client = Client::where('email', 'marco@example.com')->first();
        $this->assertSame('marcopolo.mx', $client->domain);
        $this->assertSame('Av. Reforma 222', $client->address_street);
        $this->assertSame('CDMX', $client->address_city);
        $this->assertSame('06600', $client->address_zip);
        $this->assertSame('MX', $client->address_country);
    }

    /** El portal muestra el banner de "elegir dominio" y guarda la elección notificando al equipo. */
    public function test_portal_choose_domain_flow(): void
    {
        $service = $this->hostingService();
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.com', 'password' => bcrypt('x'), 'role' => 'admin']);

        $client = Client::create([
            'legal_name' => 'Sin Dominio SA', 'email' => 'sd@example.com',
            'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true,
        ]);
        $client->clientServices()->create(['service_id' => $service->id, 'status' => 'active']);

        // Banner visible
        $this->get("/portal/{$client->portal_token}")
            ->assertStatus(200)
            ->assertSee('Elegir mi dominio');

        // Elegir dominio
        $this->post("/portal/{$client->portal_token}/domain/choose", [
            'domain' => 'MiNuevoDominio.com', 'domain_type' => 'cosmotown',
        ])->assertRedirect(route('portal.dashboard', $client->portal_token));

        $client->refresh();
        $this->assertSame('minuevodominio.com', $client->domain);
        $this->assertSame('cosmotown', $client->domain_type);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'domain_chosen']);

        // Banner desaparece (ya hay dominio)
        $this->get("/portal/{$client->portal_token}")->assertDontSee('Elegir mi dominio');
    }

    /** Regresión (review): el CP del WHOIS jamás pisa el CP fiscal usado para el CFDI. */
    public function test_whois_zip_never_overwrites_fiscal_zip(): void
    {
        $service = $this->hostingService();

        $this->post("/buy/{$service->slug}/pay/transfer", [
            'name'               => 'Fiscal Cliente',
            'email'              => 'fiscal@example.com',
            'domain'             => 'fiscalcliente.mx',
            'domain_type'        => 'cosmotown',
            'billing_preference' => 'fiscal',
            'tax_id'             => 'XAXX010101000',
            'fiscal_name'        => 'Fiscal Cliente SA',
            'address_zip'        => '06600',
            'reg_street'         => 'Calle WHOIS 1',
            'reg_zip'            => '64000',
        ])->assertRedirect();

        $client = Client::where('email', 'fiscal@example.com')->first();
        $this->assertSame('06600', $client->address_zip, 'El CP fiscal debe prevalecer sobre el del WHOIS');
        $this->assertSame('Calle WHOIS 1', $client->address_street);
    }

    /** Regresión (review): sin paquete esperando dominio, el portal no acepta elegir dominio. */
    public function test_choose_domain_requires_awaiting_package(): void
    {
        $client = Client::create([
            'legal_name' => 'Sin Compras SA', 'email' => 'nada@example.com',
            'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true,
        ]);

        $this->from("/portal/{$client->portal_token}")
            ->post("/portal/{$client->portal_token}/domain/choose", [
                'domain' => 'intruso.com', 'domain_type' => 'own',
            ])->assertRedirect();

        $this->assertNull($client->fresh()->domain);
        $this->assertDatabaseMissing('notifications', ['type' => 'domain_chosen']);
    }

    /** No se puede cambiar el dominio desde el portal si ya está registrado/activo. */
    public function test_choose_domain_blocked_when_already_registered(): void
    {
        $client = Client::create([
            'legal_name' => 'Ya Activo SA', 'email' => 'ya@example.com',
            'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true,
            'domain' => 'yaactivo.com', 'domain_type' => 'cosmotown', 'cosmotown_registered' => true,
        ]);

        $this->from("/portal/{$client->portal_token}")
            ->post("/portal/{$client->portal_token}/domain/choose", [
                'domain' => 'otro.com', 'domain_type' => 'own',
            ])->assertRedirect();

        $this->assertSame('yaactivo.com', $client->fresh()->domain);
    }

    /** contactFromClient: datos del cliente primero, respaldo de empresa para lo que falte. */
    public function test_cosmotown_contact_builder_with_fallbacks(): void
    {
        Setting::set('company_name', 'Mosley Digital');
        Setting::set('company_email', 'hola@mosley.digital');
        Setting::set('company_phone', '+5281000000');
        Setting::set('company_address', 'Oficina Central 1');

        $full = new Client([
            'name' => 'Ana Sofia Robles', 'legal_name' => 'Robles SA',
            'email' => 'ana@robles.mx', 'phone' => '+5281123', 'address_street' => 'Calle 1',
            'address_exterior' => '23', 'address_city' => 'MTY', 'address_state' => 'NL',
            'address_zip' => '64000', 'address_country' => 'mx',
        ]);
        $c = CosmotownService::contactFromClient($full);
        $this->assertSame('Ana', $c['firstName']);
        $this->assertSame('Sofia Robles', $c['lastName']);
        $this->assertSame('Calle 1 23', $c['address1']);
        $this->assertSame('MX', $c['country']);
        $this->assertSame('ana@robles.mx', $c['email']);

        $bare = new Client(['legal_name' => 'Solo Nombre']);
        $c2 = CosmotownService::contactFromClient($bare);
        $this->assertSame('hola@mosley.digital', $c2['email']);
        $this->assertSame('+5281000000', $c2['phone']);
        $this->assertSame('Oficina Central 1', $c2['address1']);
        $this->assertSame('MX', $c2['country']);
        $this->assertArrayNotHasKey('city', $c2);
    }
}
