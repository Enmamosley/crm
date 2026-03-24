<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('recurring_invoice_schedules');
        Schema::create('recurring_invoice_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();

            $table->string('series')->default('F');
            $table->string('payment_form')->default('03');
            $table->string('payment_method')->default('PUE');
            $table->string('use_cfdi')->default('G03');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('iva_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('frequency'); // monthly, quarterly, yearly
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->date('next_issue_date');
            $table->date('end_date')->nullable();
            $table->boolean('auto_stamp')->default(false);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_schedules');
    }
};
