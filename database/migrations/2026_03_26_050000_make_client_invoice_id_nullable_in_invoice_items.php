<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // client_invoice_id era NOT NULL; al migrar a orders ya no se requiere.
            // Los ítems nuevos solo llevan order_id.
            $table->unsignedBigInteger('client_invoice_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('client_invoice_id')->nullable(false)->change();
        });
    }
};
