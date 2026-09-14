<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Persona\Database\Factories\PhysicalAttributeFactory;

class PhysicalAttribute extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): PhysicalAttributeFactory
    {
        return PhysicalAttributeFactory::new();
    }

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