<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El catálogo de /api/v1/services es público a propósito (lo consume el agente
 * OpenClaw sin token), pero devolvía todos los servicios activos, incluidos los
 * que no están marcados como públicos en el panel.
 */
class PublicServicesApiTest extends TestCase
{
    use RefreshDatabase;

    private function service(string $name, bool $public, bool $active): Service
    {
        $category = ServiceCategory::firstOrCreate(['name' => 'Hosting'], ['slug' => 'hosting']);

        return Service::create([
            'name' => $name, 'slug' => str($name)->slug(), 'description' => 'Descripción',
            'price' => 1000, 'service_category_id' => $category->id,
            'public' => $public, 'active' => $active,
        ]);
    }

    public function test_only_public_and_active_services_are_listed(): void
    {
        $this->service('Publicado', public: true, active: true);
        $this->service('Interno', public: false, active: true);
        $this->service('Retirado', public: true, active: false);

        $response = $this->getJson('/api/v1/services')->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertSame(['Publicado'], $names->all());
    }

    public function test_response_keeps_its_documented_shape(): void
    {
        $this->service('Publicado', public: true, active: true);

        $this->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'name', 'description', 'price', 'service_category_id', 'category' => ['id', 'name']]],
            ])
            ->assertJsonPath('success', true);
    }

    /** Sigue siendo público: sin token debe responder 200. */
    public function test_endpoint_needs_no_token(): void
    {
        $this->getJson('/api/v1/services')->assertOk();
    }
}
