<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Antes, la gestión de correos del portal se activaba por nombre hardcodeado
 * ("Correo Profesional%"). Ahora es un flag configurable por servicio.
 * Backfill: los servicios que ya coincidían con el patrón quedan marcados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'email_service')) {
                $table->boolean('email_service')->default(false)->after('requires_domain');
            }
        });

        DB::table('services')
            ->where('name', 'like', 'Correo Profesional%')
            ->update(['email_service' => true]);
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'email_service')) {
                $table->dropColumn('email_service');
            }
        });
    }
};
