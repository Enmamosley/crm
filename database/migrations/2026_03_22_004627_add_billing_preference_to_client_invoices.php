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
        Schema::table('client_invoices', function (Blueprint $table) {
            // fiscal = datos normales del cliente
            // publico_general = RFC XAXX010101000 (sin identificar)
            // none = el cliente no quiere factura
            $table->enum('billing_preference', ['fiscal', 'publico_general', 'none'])
                  ->default('fiscal')
                  ->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropColumn('billing_preference');
        });
    }
};
