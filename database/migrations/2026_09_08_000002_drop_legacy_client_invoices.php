<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retira el legado de client_invoices. Las facturas viven en `orders` desde
 * 2026_03_25_100002_migrate_invoices_to_orders, que copió las filas y pobló
 * order_id en payments, invoice_items y dunning_attempts. Desde entonces la
 * tabla y las tres columnas client_invoice_id no las lee ni escribe nadie:
 * ningún modelo las declara fillable y el modelo ClientInvoice quedó huérfano.
 *
 * Antes de desplegar: php artisan db:backup (la migración es irreversible).
 */
return new class extends Migration
{
    private const TABLES = ['payments', 'invoice_items', 'dunning_attempts'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->dropLegacyColumn($table);
        }

        Schema::dropIfExists('client_invoices');
    }

    public function down(): void
    {
        // Irreversible por diseño: los datos viven en orders/fiscal_documents
        // desde 2026_03_25_100002. Restaurar desde el backup previo al deploy.
    }

    private function dropLegacyColumn(string $table): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'client_invoice_id')) {
            return;
        }

        // 1. La FK: MySQL exige soltarla antes de tocar la columna. En SQLite no
        //    tiene nombre, así que se localiza por la columna que referencia.
        $hasForeignKey = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $fk) => in_array('client_invoice_id', $fk['columns'], true));

        if ($hasForeignKey) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign(['client_invoice_id']));
        }

        // 2. Los índices que la incluyen: SQLite no permite DROP COLUMN sobre una
        //    columna indexada (dunning_attempts tiene un índice compuesto, y
        //    MySQL crea uno automático al declarar la FK).
        $indexes = [
            "{$table}_client_invoice_id_status_index",
            "{$table}_client_invoice_id_index",
            "{$table}_client_invoice_id_foreign",
        ];

        foreach ($indexes as $index) {
            if (Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
            }
        }

        // 3. La columna.
        Schema::table($table, fn (Blueprint $t) => $t->dropColumn('client_invoice_id'));
    }
};
