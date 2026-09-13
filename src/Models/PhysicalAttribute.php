<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PhysicalAttribute extends Model
{
    protected $guarded = [];

    /**
     * Resolve the table name from the package configuration.
     */
    public function getTable(): string
    {
        return config('persona.tables.physical_attributes', 'persona_physical_attributes');
    }

    public function personable(): MorphTo
    {
        return $this->morphTo();
    }
}