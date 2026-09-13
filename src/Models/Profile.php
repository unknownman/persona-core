<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Persona\Casts\ConditionalEncrypted;

class Profile extends Model
{
    protected $guarded = [];

    /**
     * Resolve the table name from the package configuration.
     */
    public function getTable(): string
    {
        return config('persona.tables.profiles', 'persona_profiles');
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => ConditionalEncrypted::class,
        ];
    }

    public function personable(): MorphTo
    {
        return $this->morphTo();
    }
}