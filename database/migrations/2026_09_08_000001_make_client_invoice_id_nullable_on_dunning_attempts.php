<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mismo caso que 2026_06_01_000002 sobre payments, que se quedó sin aplicar a
 * dunning_attempts: la tabla se referencia ahora por order_id, pero
 * client_invoice_id seguía NOT NULL, así que TODO DunningAttempt::create()
 * fallaba y el comando dunning:process no podía registrar ningún intento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('dunning_attempts', 'client_invoice_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // En MySQL hay que soltar la FK antes de modificar la columna.
            Schema::table('dunning_attempts', function (Blueprint $table) {
                $table->dropForeign(['client_invoice_id']);
            });
            Schema::table('dunning_attempts', function (Blueprint $table) {
                $table->unsignedBigInteger('client_invoice_id')->nullable()->change();
            });
            Schema::table('dunning_attempts', function (Blueprint $table) {
                $table->foreign('client_invoice_id')
                    ->references('id')->on('client_invoices')
                    ->nullOnDelete();
            });
        } else {
            // SQLite (y otros): change() reconstruye la tabla preservando datos.
            Schema::table('dunning_attempts', function (Blueprint $table) {
                $table->unsignedBigInteger('client_invoice_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // No revertimos a NOT NULL: existirían filas con client_invoice_id NULL
        // (intentos basados en order_id) que harían fallar la reversión.
    }
};
