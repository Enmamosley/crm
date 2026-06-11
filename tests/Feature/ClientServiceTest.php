<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientServiceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('secret'), 'role' => 'admin',
        ]);
    }

    private function clientAndService(): array
    {
        $client = Client::create([
            'legal_name' => 'Cliente WhatsApp', 'email' => 'wa@test.com',
            'tax_system' => '616', 'cfdi_use' => 'S01', 'portal_active' => true,
        ]);
        $cat = ServiceCategory::create(['name' => 'Hosting', 'slug' => 'hosting', 'active' => true]);
        $service = Service::create([
            'service_category_id' => $cat->id, 'name' => 'Correo Profesional 1 Año', 'price' => 1550,
        ]);

        return [$client, $service];
    }

    /** Se puede asignar un servicio a un cliente SIN crear orden/factura. */
    public function test_admin_can_assign_service_without_invoice(): void
    {
        [$client, $service] = $this->clientAndService();

        $response = $this->actingAs($this->admin())->post("/panel/clients/{$client->id}/services", [
            'service_id' => $service->id,
            'price'      => 999.50,
            'source'     => 'whatsapp',
            'notes'      => 'Venta acordada por WhatsApp',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('client_services', [
            'client_id' => $client->id, 'service_id' => $service->id,
            'status' => 'active', 'source' => 'whatsapp',
        ]);
        $this->assertSame(0, $client->invoices()->count(), 'No debe crearse ninguna orden/factura');
        $this->assertSame(999.50, $client->clientServices()->first()->effectivePrice());
    }

    /** El precio pactado es opcional: sin él aplica el de catálogo. */
    public function test_effective_price_falls_back_to_catalog(): void
    {
        [$client, $service] = $this->clientAndService();

        $cs = $client->clientServices()->create(['service_id' => $service->id]);

        $this->assertSame(1550.0, $cs->effectivePrice());
    }

    /** Suspender y retirar el servicio. */
    public function test_admin_can_suspend_and_remove_service(): void
    {
        [$client, $service] = $this->clientAndService();
        $cs = $client->clientServices()->create(['service_id' => $service->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/panel/client-services/{$cs->id}", ['status' => 'suspended'])
            ->assertRedirect();
        $this->assertSame('suspended', $cs->fresh()->status);

        $this->actingAs($admin)->delete("/panel/client-services/{$cs->id}")->assertRedirect();
        $this->assertDatabaseMissing('client_services', ['id' => $cs->id]);
    }

    /** El portal detecta el servicio de correo asignado manualmente (venta WhatsApp). */
    public function test_manual_email_service_unlocks_portal_detection(): void
    {
        [$client, $service] = $this->clientAndService();
        $client->clientServices()->create(['service_id' => $service->id, 'status' => 'active']);

        // El dashboard del portal debe mostrar la sección "Mis servicios" con el servicio activo
        $response = $this->get("/portal/{$client->portal_token}");

        $response->assertStatus(200);
        $response->assertSee('Mis servicios');
        $response->assertSee('Correo Profesional 1');
    }
}
