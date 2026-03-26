<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE client_invoices MODIFY COLUMN status ENUM('draft','sent','pending','valid','cancelled') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE client_invoices MODIFY COLUMN status ENUM('draft','pending','valid','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
