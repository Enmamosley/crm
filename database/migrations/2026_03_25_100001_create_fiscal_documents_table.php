<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('facturapi_invoice_id')->nullable()->index();
            $table->json('facturapi_data')->nullable();
            $table->string('status')->default('pending'); // pending | valid | cancelled
            $table->string('cancellation_motive')->nullable();
            $table->timestamp('stamped_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
    }
};
