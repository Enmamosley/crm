<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index('email');
            $table->index('phone');
            $table->index('status');
            $table->index('source');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->index('slug');
            $table->index('active');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index('email');
            $table->index('tax_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('status');
            $table->index('paid_at');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->index('status');
            $table->index('valid_until');
        });

        Schema::table('client_invoices', function (Blueprint $table) {
            $table->index('status');
            $table->index('stamped_at');
            $table->index('paid_at');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['status']);
            $table->dropIndex(['source']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropIndex(['active']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['tax_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['paid_at']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['valid_until']);
        });

        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['stamped_at']);
            $table->dropIndex(['paid_at']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropIndex(['key']);
        });
    }
};
