<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('twentyi_package_bundle_id')->nullable()->after('requires_domain')
                  ->comment('ID del bundle type en 20i. Al comprarse, se crea el hosting automáticamente.');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('twentyi_package_bundle_id');
        });
    }
};
