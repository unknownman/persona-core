<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persona.tables.document_files', 'persona_document_files'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained(config('persona.tables.documents', 'persona_documents'))
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('disk')->default('local');
            $table->string('side')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('persona.tables.document_files', 'persona_document_files'));
    }
};