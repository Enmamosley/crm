<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE clients MODIFY lead_id BIGINT UNSIGNED NULL DEFAULT NULL');
        DB::statement('ALTER TABLE clients MODIFY tax_id VARCHAR(255) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE clients MODIFY address_zip VARCHAR(255) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE clients MODIFY lead_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE clients MODIFY tax_id VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE clients MODIFY address_zip VARCHAR(255) NOT NULL');
    }
};
