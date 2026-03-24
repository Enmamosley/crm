<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->change();
            $table->string('tax_id')->nullable()->change();
            $table->string('address_zip')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable(false)->change();
            $table->string('tax_id')->nullable(false)->change();
            $table->string('address_zip')->nullable(false)->change();
        });
    }
};
