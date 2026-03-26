<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Migrar client_invoices → orders (con mapeo de status)
        DB::statement("
            INSERT INTO orders
                (id, client_id, quote_id, series, folio_number, payment_form, payment_method,
                 use_cfdi, subtotal, iva_amount, total, notes,
                 status, created_at, updated_at)
            SELECT
                id, client_id, quote_id, series, folio_number, payment_form, payment_method,
                use_cfdi, subtotal, iva_amount, total, notes,
                CASE
                    WHEN status = 'valid'    THEN 'sent'
                    WHEN status = 'pending'  THEN 'pending'
                    WHEN status = 'cancelled' THEN 'cancelled'
                    ELSE status
                END,
                created_at, updated_at
            FROM client_invoices
        ");

        // 2. Crear fiscal_documents para las facturas timbradas
        DB::statement("
            INSERT INTO fiscal_documents
                (order_id, facturapi_invoice_id, facturapi_data, status,
                 stamped_at, cancelled_at, created_at, updated_at)
            SELECT
                id,
                facturapi_invoice_id,
                facturapi_data,
                CASE
                    WHEN status = 'cancelled' THEN 'cancelled'
                    WHEN status IN ('valid', 'pending') THEN status
                    ELSE 'valid'
                END,
                stamped_at,
                cancelled_at,
                created_at,
                updated_at
            FROM client_invoices
            WHERE facturapi_invoice_id IS NOT NULL
        ");

        // 3. Agregar order_id a invoice_items y poblar desde client_invoice_id
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('id');
        });
        DB::statement('UPDATE invoice_items SET order_id = client_invoice_id');

        // 4. Agregar order_id a payments y poblar
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('id');
        });
        DB::statement('UPDATE payments SET order_id = client_invoice_id');

        // 5. Agregar order_id a dunning_attempts y poblar
        Schema::table('dunning_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('id');
        });
        DB::statement('UPDATE dunning_attempts SET order_id = client_invoice_id');
    }

    public function down(): void
    {
        Schema::table('dunning_attempts', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
        DB::statement('DELETE FROM fiscal_documents');
        DB::statement('DELETE FROM orders');
    }
};
