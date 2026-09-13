<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persona.tables.legal_details', 'persona_legal_details'), function (Blueprint $table) {
            $table->id();
            $table->morphs('personable');
            $table->string('nationality', 2)->nullable();
            $table->string('marital_status')->nullable();
            $table->text('tax_id')->nullable();
            $table->string('tax_id_hash')->nullable();
            $table->timestamps();

            $table->index('tax_id_hash');

            $table->unique(
                ['personable_type', 'personable_id'],
                'persona_legal_detail_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('persona.tables.legal_details', 'persona_legal_details'));
    }
};