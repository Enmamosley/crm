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
        Schema::dropIfExists('client_documents');
        Schema::create('client_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');           // Nombre visible del documento
            $table->string('file_path');      // Ruta en storage
            $table->string('file_type')->nullable();  // MIME type
            $table->unsignedBigInteger('file_size')->nullable(); // Bytes
            $table->string('uploaded_by')->nullable(); // Usuario admin que lo subió
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_documents');
    }
};
