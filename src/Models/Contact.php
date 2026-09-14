<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Persona\Casts\ConditionalEncrypted;
use Persona\Casts\LookupHash;
use Persona\Database\Factories\ContactFactory;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    /**
     * Resolve the table name from the package configuration.
     */
    public function getTable(): string
    {
        return config('persona.tables.contacts', 'persona_contacts');
    }

    protected function casts(): array
    {
        return [
            'value' => ConditionalEncrypted::class,
            'value_hash' => LookupHash::class,
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'is_emergency' => 'boolean',
        ];
    }

    public function personable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeEmergency(Builder $query): Builder
    {
        return $query->where('is_emergency', true);
    }
}