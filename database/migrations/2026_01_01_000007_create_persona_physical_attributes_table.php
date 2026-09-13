<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persona.tables.physical_attributes', 'persona_physical_attributes'), function (Blueprint $table) {
            $table->id();
            $table->morphs('personable');
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('weight')->nullable();
            $table->string('eye_color')->nullable();
            $table->string('hair_color')->nullable();
            $table->string('blood_type')->nullable();
            $table->timestamps();

            $table->unique(
                ['personable_type', 'personable_id'],
                'persona_physical_attribute_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('persona.tables.physical_attributes', 'persona_physical_attributes'));
    }
};