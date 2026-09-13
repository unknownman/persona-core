<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persona.tables.profiles', 'persona_profiles'), function (Blueprint $table) {
            $table->id();
            $table->morphs('personable');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->text('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('locale')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamps();

            $table->unique(
                ['personable_type', 'personable_id'],
                'persona_profile_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('persona.tables.profiles', 'persona_profiles'));
    }
};