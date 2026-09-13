<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Persona\Casts\ConditionalEncrypted;
use Persona\Casts\LookupHash;

class LegalDetail extends Model
{
    protected $guarded = [];

    /**
     * Resolve the table name from the package configuration.
     */
    public function getTable(): string
    {
        return config('persona.tables.legal_details', 'persona_legal_details');
    }

    protected function casts(): array
    {
        return [
            'tax_id' => ConditionalEncrypted::class,
            'tax_id_hash' => LookupHash::class,
        ];
    }

    public function personable(): MorphTo
    {
        return $this->morphTo();
    }
}