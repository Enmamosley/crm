<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servicios contratados por cliente SIN pasar por orden/factura.
 * Cubre ventas hechas fuera del flujo (WhatsApp, acuerdos directos) y da una
 * fuente de verdad para "qué servicios tiene este cliente".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2)->nullable();   // precio pactado; null = precio de catálogo
            $table->string('status', 20)->default('active'); // active | suspended | cancelled
            $table->date('started_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('source', 30)->default('manual'); // manual | whatsapp | order
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_services');
    }
};
