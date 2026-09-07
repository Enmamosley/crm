<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recibos Electrónicos de Pago (complemento de pago, CFDI tipo P).
 *
 * Una factura timbrada como PPD obliga a emitir un REP por cada pago recibido.
 * El CRM no emitía ninguno: FacturapiService tenía el método escrito y nadie lo
 * llamaba, y con Finkok ni siquiera existía. Aquí queda constancia de cada
 * intento, de modo que un complemento pendiente o fallido se vea en el panel en
 * lugar de perderse.
 *
 * `payment_id` es único: un pago genera un REP y sólo uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_complements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('source', 20)->default('facturapi'); // facturapi | finkok
            $table->string('provider_id')->nullable()->index();
            $table->string('uuid', 40)->nullable();
            $table->string('status', 20)->default('pending');   // pending | valid | failed
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('installment')->default(1);
            $table->json('data')->nullable();
            $table->text('error')->nullable();
            $table->string('xml_path')->nullable();
            $table->timestamp('stamped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_complements');
    }
};
