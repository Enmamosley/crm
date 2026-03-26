<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Nombre comercial / de contacto (distinto del nombre fiscal)
            $table->string('name')->nullable()->after('id');
        });

        // Rellenar name con legal_name para registros existentes
        \Illuminate\Support\Facades\DB::statement('UPDATE clients SET name = legal_name WHERE name IS NULL');
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
