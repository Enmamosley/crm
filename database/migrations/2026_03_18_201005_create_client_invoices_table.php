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
        Schema::create('client_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();

            // Datos de la factura
            $table->string('series')->default('F');
            $table->unsignedInteger('folio_number')->nullable();
            $table->string('payment_form')->default('03');  // Transferencia por defecto
            $table->string('payment_method')->default('PUE'); // Pago en una sola exhibición
            $table->string('use_cfdi')->default('G03');

            // Estado y datos FacturAPI
            $table->enum('status', ['draft', 'pending', 'valid', 'cancelled'])->default('draft');
            $table->string('facturapi_invoice_id')->nullable();
            $table->json('facturapi_data')->nullable();  // Respuesta completa de FacturAPI

            // Montos (espejo de la cotización al momento de facturar)
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('iva_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamp('stamped_at')->nullable();  // Cuando fue timbrada
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_invoices');
    }
};
