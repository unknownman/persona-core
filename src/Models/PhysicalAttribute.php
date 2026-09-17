<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Persona\Database\Factories\PhysicalAttributeFactory;
use Persona\Traits\BelongsToPersonable;

class PhysicalAttribute extends Model
{
    use BelongsToPersonable, HasFactory;

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

    protected function casts(): array
    {
        return [
            'height' => 'integer',
            'weight' => 'integer',
        ];
    }
}