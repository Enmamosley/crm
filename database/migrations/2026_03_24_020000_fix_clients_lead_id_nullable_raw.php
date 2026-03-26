<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use raw SQL only on MySQL/MariaDB; SQLite doesn't support ALTER COLUMN
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('ALTER TABLE clients MODIFY lead_id BIGINT UNSIGNED NULL DEFAULT NULL');
        DB::statement('ALTER TABLE clients MODIFY tax_id VARCHAR(255) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE clients MODIFY address_zip VARCHAR(255) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('ALTER TABLE clients MODIFY lead_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE clients MODIFY tax_id VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE clients MODIFY address_zip VARCHAR(255) NOT NULL');
    }
};
