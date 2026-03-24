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
        Schema::dropIfExists('invoice_items');
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('sat_product_key')->default('80101501'); // Servicios profesionales (SAT genérico)
            $table->string('sat_unit_key')->default('E48');         // E48 = Servicio
            $table->string('sat_unit_name')->default('Servicio');
            $table->string('tax_object')->default('02');            // 02 = Sí objeto de impuesto
            $table->boolean('iva_exempt')->default(false);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
