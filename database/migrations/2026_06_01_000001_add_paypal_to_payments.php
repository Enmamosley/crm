<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'gateway')) {
                $table->string('gateway', 30)->default('mercadopago')->after('order_id');
            }
            if (!Schema::hasColumn('payments', 'paypal_order_id')) {
                $table->string('paypal_order_id')->nullable()->unique()->after('mp_payment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'paypal_order_id')) {
                $table->dropUnique(['paypal_order_id']);
                $table->dropColumn('paypal_order_id');
            }
            if (Schema::hasColumn('payments', 'gateway')) {
                $table->dropColumn('gateway');
            }
        });
    }
};
