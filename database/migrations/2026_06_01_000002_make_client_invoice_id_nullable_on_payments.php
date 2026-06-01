<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los pagos se referencian ahora por order_id; client_invoice_id es legado.
 * Estaba NOT NULL, lo que hacía fallar TODO Payment::create() (sólo se escribe order_id).
 * Lo hacemos nullable de forma compatible con MySQL y SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payments', 'client_invoice_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // En MySQL hay que soltar la FK antes de modificar la columna.
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['client_invoice_id']);
            });
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('client_invoice_id')->nullable()->change();
            });
            Schema::table('payments', function (Blueprint $table) {
                $table->foreign('client_invoice_id')
                    ->references('id')->on('client_invoices')
                    ->nullOnDelete();
            });
        } else {
            // SQLite (y otros): change() reconstruye la tabla preservando datos.
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('client_invoice_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // No revertimos a NOT NULL: existirían filas con client_invoice_id NULL
        // (pagos basados en order_id) que harían fallar la reversión.
    }
};
