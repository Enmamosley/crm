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
        Schema::dropIfExists('agent_controls');
        Schema::create('agent_controls', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->default('general')->comment('Canal: general, chat, lead_id');
            $table->enum('action', ['paused', 'reactivated']);
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_controls');
    }
};
