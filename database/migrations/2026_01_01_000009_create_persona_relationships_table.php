<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persona.tables.relationships', 'persona_relationships'), function (Blueprint $table) {
            $table->id();
            $table->morphs('personable');
            $table->morphs('related_personable');
            $table->string('type', 100);
            $table->timestamps();

            $table->unique(
                [
                    'personable_type',
                    'personable_id',
                    'related_personable_type',
                    'related_personable_id',
                    'type',
                ],
                'persona_relation_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('persona.tables.relationships', 'persona_relationships'));
    }
};