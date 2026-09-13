<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persona.tables.documents', 'persona_documents'), function (Blueprint $table) {
            $table->id();
            $table->morphs('personable');
            $table->string('type');
            $table->text('number');
            $table->string('number_hash');
            $table->string('country_code', 2)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['personable_type', 'personable_id', 'type', 'country_code', 'number_hash'],
                'persona_document_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('persona.tables.documents', 'persona_documents'));
    }
};