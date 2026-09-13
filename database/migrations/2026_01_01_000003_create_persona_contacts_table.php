<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persona.tables.contacts', 'persona_contacts'), function (Blueprint $table) {
            $table->id();
            $table->morphs('personable');
            $table->string('type');
            $table->text('value');
            $table->string('value_hash');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('value_hash');
            $table->unique(
                ['personable_type', 'personable_id', 'type', 'value_hash'],
                'persona_contact_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('persona.tables.contacts', 'persona_contacts'));
    }
};