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
        Schema::dropIfExists('clients');
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            // Datos de identificación
            $table->string('legal_name');           // Nombre fiscal (razón social)
            $table->string('tax_id');               // RFC
            $table->string('tax_system')->default('626'); // Régimen fiscal SAT
            $table->string('cfdi_use')->default('G03');   // Uso CFDI por defecto
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Dirección fiscal
            $table->string('address_zip');
            $table->string('address_street')->nullable();
            $table->string('address_exterior')->nullable();
            $table->string('address_interior')->nullable();
            $table->string('address_neighborhood')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_municipality')->nullable();
            $table->string('address_state')->nullable();
            $table->string('address_country')->default('MEX');

            // FacturAPI
            $table->string('facturapi_customer_id')->nullable();

            // Portal cliente
            $table->string('portal_token')->unique();  // UUID para acceso al portal
            $table->boolean('portal_active')->default(true);

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
