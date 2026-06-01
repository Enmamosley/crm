<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Regresión: los campos fiscales SAT deben persistir (estaban fuera de $fillable). */
    public function test_service_persists_sat_fields(): void
    {
        $category = ServiceCategory::create([
            'name' => 'Hosting', 'slug' => 'hosting', 'active' => true,
        ]);

        $service = Service::create([
            'service_category_id' => $category->id,
            'name'            => 'Plan Pro',
            'price'           => 1000,
            'sat_product_key' => '81111500',
            'sat_unit_key'    => 'E48',
            'sat_unit_name'   => 'Servicio profesional',
            'tax_object'      => '02',
            'iva_exempt'      => true,
        ]);

        $fresh = $service->fresh();
        $this->assertSame('81111500', $fresh->sat_product_key);
        $this->assertSame('E48', $fresh->sat_unit_key);
        $this->assertSame('Servicio profesional', $fresh->sat_unit_name);
        $this->assertSame('02', $fresh->tax_object);
        $this->assertTrue($fresh->iva_exempt);
    }
}
