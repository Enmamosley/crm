<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailServiceFlagTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('secret'), 'role' => 'admin',
        ]);
    }

    private function makeService(string $name, bool $emailService): Service
    {
        $cat = ServiceCategory::firstOrCreate(
            ['slug' => 'hosting'],
            ['name' => 'Hosting', 'active' => true]
        );

        return Service::create([
            'service_category_id' => $cat->id,
            'name' => $name, 'price' => 1000, 'email_service' => $emailService,
        ]);
    }

    private function clientWith(Service $service): Client
    {
        $client = Client::create([
            'legal_name' => 'Cliente Correo', 'email' => 'cc@test.com',
            'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true,
        ]);
        $client->clientServices()->create(['service_id' => $service->id, 'status' => 'active']);

        return $client;
    }

    /** El flag se guarda desde el formulario del panel y se puede quitar. */
    public function test_email_service_flag_persists_via_admin_forms(): void
    {
        $admin = $this->admin();
        $cat = ServiceCategory::create(['name' => 'Hosting', 'slug' => 'hosting', 'active' => true]);

        $this->actingAs($admin)->post('/panel/services', [
            'service_category_id' => $cat->id,
            'name' => 'Paquete Empresarial', 'price' => 5000,
            'email_service' => '1',
        ])->assertRedirect();

        $service = Service::where('name', 'Paquete Empresarial')->first();
        $this->assertTrue($service->email_service);

        // Desmarcar el checkbox (no se envía el campo) lo apaga
        $this->actingAs($admin)->put("/panel/services/{$service->id}", [
            'service_category_id' => $cat->id,
            'name' => 'Paquete Empresarial', 'price' => 5000,
        ])->assertRedirect();

        $this->assertFalse($service->fresh()->email_service);
    }

    /** Cualquier paquete con el flag activa la gestión de correos — sin importar el nombre. */
    public function test_any_service_with_flag_enables_email_management(): void
    {
        $service = $this->makeService('Plan Corporativo Total', true);
        $client  = $this->clientWith($service);

        // Pasa el guard del flag (403); 200 con buzones vacíos al no haber 20i configurado
        $this->get("/portal/{$client->portal_token}/mailboxes")->assertStatus(200);
    }

    /** Un servicio llamado "Correo Profesional..." SIN el flag ya no activa nada (no más hardcode). */
    public function test_name_alone_no_longer_enables_email_management(): void
    {
        $service = $this->makeService('Correo Profesional 1 Año', false);
        $client  = $this->clientWith($service);

        $this->get("/portal/{$client->portal_token}/mailboxes")->assertStatus(403);
    }

    /** La compra directa pagada de un paquete con flag también lo activa. */
    public function test_paid_direct_purchase_with_flag_enables_email(): void
    {
        $service = $this->makeService('Mega Paquete Pyme', true);
        $client = Client::create([
            'legal_name' => 'Compra Directa SA', 'email' => 'cd@test.com',
            'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true,
        ]);
        $client->invoices()->create([
            'series' => 'V', 'payment_form' => '04', 'payment_method' => 'PUE', 'use_cfdi' => 'S01',
            'status' => 'sent', 'subtotal' => 1000, 'iva_amount' => 160, 'total' => 1160,
            'paid_at' => now(), 'notes' => 'Compra directa: Mega Paquete Pyme',
        ]);

        $this->get("/portal/{$client->portal_token}/mailboxes")->assertStatus(200);

        // Pero un nombre PARECIDO no debe matchear (sin falsos positivos por substring)
        $client2 = Client::create([
            'legal_name' => 'Otro SA', 'email' => 'otro@test.com',
            'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true,
        ]);
        $client2->invoices()->create([
            'series' => 'V', 'payment_form' => '04', 'payment_method' => 'PUE', 'use_cfdi' => 'S01',
            'status' => 'sent', 'subtotal' => 1000, 'iva_amount' => 160, 'total' => 1160,
            'paid_at' => now(), 'notes' => 'Compra directa: Mega Paquete Pyme Plus Sin Correo',
        ]);

        $this->get("/portal/{$client2->portal_token}/mailboxes")->assertStatus(403);
    }
}
