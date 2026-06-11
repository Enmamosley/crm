<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite registrar CFDIs timbrados FUERA del CRM (otro PAC/sistema):
 * source='external' + archivos XML/PDF guardados en disco privado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('fiscal_documents', 'source')) {
                $table->string('source', 20)->default('facturapi')->after('facturapi_invoice_id');
            }
            if (!Schema::hasColumn('fiscal_documents', 'uuid')) {
                $table->string('uuid', 40)->nullable()->after('source');
            }
            if (!Schema::hasColumn('fiscal_documents', 'pdf_path')) {
                $table->string('pdf_path')->nullable()->after('uuid');
            }
            if (!Schema::hasColumn('fiscal_documents', 'xml_path')) {
                $table->string('xml_path')->nullable()->after('pdf_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            foreach (['xml_path', 'pdf_path', 'uuid', 'source'] as $col) {
                if (Schema::hasColumn('fiscal_documents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
