<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ServiceCategory;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::create([
            'name' => 'Admin',
            'email' => 'admin@crm.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Settings
        Setting::set('company_name', 'Mi Empresa');
        Setting::set('company_email', 'contacto@miempresa.com');
        Setting::set('company_phone', '+52 55 1234 5678');
        Setting::set('company_address', 'Ciudad de México, México');
        Setting::set('company_rfc', 'XAXX010101000');
        Setting::set('iva_percentage', '16');

        // Service Categories
        $web = ServiceCategory::create(['name' => 'Desarrollo Web', 'slug' => 'web', 'description' => 'Servicios de desarrollo web']);
        $apps = ServiceCategory::create(['name' => 'Aplicaciones Móviles', 'slug' => 'apps', 'description' => 'Desarrollo de apps móviles']);
        $soporte = ServiceCategory::create(['name' => 'Soporte Técnico', 'slug' => 'soporte', 'description' => 'Servicios de soporte y mantenimiento']);

        // Services
        Service::create(['service_category_id' => $web->id, 'name' => 'Landing Page', 'description' => 'Página de aterrizaje optimizada para conversión', 'price' => 5000]);
        Service::create(['service_category_id' => $web->id, 'name' => 'Sitio Web Corporativo', 'description' => 'Sitio web completo hasta 10 páginas', 'price' => 15000]);
        Service::create(['service_category_id' => $web->id, 'name' => 'E-Commerce', 'description' => 'Tienda en línea con carrito y pagos', 'price' => 25000]);
        Service::create(['service_category_id' => $web->id, 'name' => 'Sistema Web a Medida', 'description' => 'Desarrollo de sistema web personalizado', 'price' => 40000]);

        Service::create(['service_category_id' => $apps->id, 'name' => 'App iOS', 'description' => 'Aplicación nativa para iOS', 'price' => 35000]);
        Service::create(['service_category_id' => $apps->id, 'name' => 'App Android', 'description' => 'Aplicación nativa para Android', 'price' => 35000]);
        Service::create(['service_category_id' => $apps->id, 'name' => 'App Multiplataforma', 'description' => 'App para iOS y Android con Flutter/React Native', 'price' => 50000]);

        Service::create(['service_category_id' => $soporte->id, 'name' => 'Soporte Mensual Básico', 'description' => 'Mantenimiento y soporte básico mensual', 'price' => 3000]);
        Service::create(['service_category_id' => $soporte->id, 'name' => 'Soporte Mensual Premium', 'description' => 'Soporte prioritario con SLA garantizado', 'price' => 8000]);
        Service::create(['service_category_id' => $soporte->id, 'name' => 'Consultoría Técnica (hora)', 'description' => 'Asesoría técnica por hora', 'price' => 800]);

        // Create API token for agent
        $user = User::first();
        $token = $user->createToken('openclaw-agent');
        echo "\n========================================\n";
        echo "API TOKEN PARA OPENCLAW: " . $token->plainTextToken . "\n";
        echo "========================================\n\n";
    }
}
