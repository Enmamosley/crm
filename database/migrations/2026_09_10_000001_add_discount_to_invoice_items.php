<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descuento por línea.
 *
 * El carrito aplica el cupón sobre el pedido entero. Para que el CFDI lo
 * refleje como manda el SAT —un `Descuento` en cada concepto, no un total
 * rebajado a mano— hace falta guardar qué parte del descuento tocó a cada
 * línea. Sin él, el IVA de una compra con cupón y servicios exentos mezclados
 * no cuadraría con lo cobrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'discount')) {
                $table->decimal('discount', 12, 2)->default(0)->after('unit_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'discount')) {
                $table->dropColumn('discount');
            }
        });
    }
};
