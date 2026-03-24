<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Clave SAT del producto/servicio (catálogo c_ClaveProdServ)
            // Ej: '81161500' = Servicios tecnología, '43231513' = Software, '80101500' = Consultoría
            $table->string('sat_product_key', 10)->nullable()->after('twentyi_package_bundle_id')
                  ->comment('Clave SAT c_ClaveProdServ');

            // Clave de unidad SAT (catálogo c_ClaveUnidad)
            // Ej: 'E48' = Servicio, 'H87' = Pieza, 'MTK' = m², 'LTR' = Litro
            $table->string('sat_unit_key', 10)->nullable()->default('E48')->after('sat_product_key')
                  ->comment('Clave SAT c_ClaveUnidad');

            // Nombre de la unidad (aparece en la factura PDF)
            $table->string('sat_unit_name', 50)->nullable()->default('Servicio')->after('sat_unit_key');

            // Objeto de impuesto SAT: '01' No objeto, '02' Sí objeto (default), '03' Sí objeto no obligado retención
            $table->string('tax_object', 2)->nullable()->default('02')->after('sat_unit_name')
                  ->comment('Clave SAT c_ObjetoImp');

            // Si el servicio está exento de IVA (tasa 0 en lugar de la configurada)
            $table->boolean('iva_exempt')->default(false)->after('tax_object');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['sat_product_key', 'sat_unit_key', 'sat_unit_name', 'tax_object', 'iva_exempt']);
        });
    }
};
