<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el cupón aplicado en la orden para poder consumir su uso cuando el
 * pago se confirma (p.ej. webhook OXXO/SPEI sin acceso a la sesión).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'discount_code')) {
                $table->string('discount_code', 50)->nullable()->after('total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'discount_code')) {
                $table->dropColumn('discount_code');
            }
        });
    }
};
