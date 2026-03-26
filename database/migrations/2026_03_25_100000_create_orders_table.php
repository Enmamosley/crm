<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->nullOnDelete()->constrained();
            $table->string('series')->default('F');
            $table->unsignedInteger('folio_number')->nullable();
            $table->string('payment_form')->default('03');
            $table->string('payment_method')->default('PUE');
            $table->string('use_cfdi')->default('G03');
            $table->string('billing_preference')->default('fiscal'); // fiscal | publico_general | none
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('iva_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status')->default('draft'); // draft | sent | pending | paid | cancelled
            $table->timestamp('paid_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
