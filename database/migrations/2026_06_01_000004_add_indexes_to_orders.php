<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla orders se creó sin índices en columnas calientes. Dashboard, reportes
 * y listados filtran/ordenan por status, paid_at y created_at → full table scans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status', 'orders_status_index');
            $table->index('paid_at', 'orders_paid_at_index');
            $table->index('created_at', 'orders_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_index');
            $table->dropIndex('orders_paid_at_index');
            $table->dropIndex('orders_created_at_index');
        });
    }
};
