<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payments');
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('mp_payment_id')->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('MXN');
            $table->enum('status', ['pending', 'approved', 'in_process', 'rejected', 'refunded', 'cancelled'])->default('pending');
            $table->string('status_detail')->nullable();
            $table->string('payment_type')->nullable();      // credit_card, debit_card, ticket, bank_transfer
            $table->string('payment_method_id')->nullable();  // visa, master, oxxo, banamex, etc.
            $table->json('mp_data')->nullable();               // respuesta completa de MP
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // Agregar campo paid_at a client_invoices
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
        Schema::dropIfExists('payments');
    }
};
