<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Persona\Database\Factories\AddressFactory;
use Persona\Traits\BelongsToPersonable;

class Address extends Model
{
    use BelongsToPersonable, HasFactory;

    protected $guarded = [];

    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }

    /**
     * Resolve the table name from the package configuration.
     */
    public function getTable(): string
    {
        return config('persona.tables.addresses', 'persona_addresses');
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }
}