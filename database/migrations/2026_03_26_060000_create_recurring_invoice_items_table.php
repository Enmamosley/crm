<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // recurring_invoice_schedules se crea después (2026_06_15_000003), así que
        // en una instalación nueva la tabla referenciada todavía no existe. SQLite
        // lo tolera, pero MySQL aborta con errno 150 y deja la migración a medias.
        // La FK la coloca 2026_06_15_000004, que recrea esta tabla ya con destino.
        $canConstrain = Schema::hasTable('recurring_invoice_schedules');

        Schema::dropIfExists('recurring_invoice_items');
        Schema::create('recurring_invoice_items', function (Blueprint $table) use ($canConstrain) {
            $table->id();
            $column = $table->foreignId('recurring_invoice_schedule_id');
            if ($canConstrain) {
                $column->constrained()->cascadeOnDelete();
            }
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
        Schema::dropIfExists('recurring_invoice_items');
    }
};
