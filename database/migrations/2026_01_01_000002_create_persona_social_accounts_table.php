<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persona.tables.social_accounts', 'persona_social_accounts'), function (Blueprint $table) {
            $table->id();
            $table->morphs('personable');
            $table->string('platform');
            $table->string('username');
            $table->string('url')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(
                ['personable_type', 'personable_id', 'platform', 'username'],
                'persona_social_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('persona.tables.social_accounts', 'persona_social_accounts'));
    }
};