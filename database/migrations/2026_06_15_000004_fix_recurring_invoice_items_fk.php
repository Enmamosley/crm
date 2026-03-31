<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Garantiza que recurring_invoice_items tenga la estructura correcta
        // independientemente del orden en que corrieron las migraciones anteriores.
        //
        // En instalaciones nuevas: _items se creó sin FK (falló el constrained())
        // pero la tabla existe vacía. Aquí la recreamos limpia.
        // En el VPS existente: ya está correcta, esta migración no toca datos.

        $hasTable = Schema::hasTable('recurring_invoice_items');
        $hasFk = false;

        if ($hasTable) {
            $fks = DB::select("
                SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'recurring_invoice_items'
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                  AND CONSTRAINT_NAME = 'recurring_invoice_items_recurring_invoice_schedule_id_foreign'
            ");
            $hasFk = count($fks) > 0;
        }

        if ($hasTable && $hasFk) {
            // VPS existente: ya está bien, no hacer nada
            return;
        }

        // Instalación nueva: recrear la tabla con estructura correcta
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('recurring_invoice_items');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Schema::create('recurring_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_invoice_schedule_id')
                  ->constrained('recurring_invoice_schedules')
                  ->cascadeOnDelete();
            $table->string('description');
            $table->string('sat_product_key')->default('80101501');
            $table->string('sat_unit_key')->default('E48');
            $table->string('sat_unit_name')->default('Servicio');
            $table->string('tax_object')->default('02');
            $table->boolean('iva_exempt')->default(false);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // No revertir — la tabla original la maneja su propia migración
    }
};
