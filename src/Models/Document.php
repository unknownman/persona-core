<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Persona\Casts\ConditionalEncrypted;
use Persona\Casts\LookupHash;
use Persona\Database\Factories\DocumentFactory;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function newFactory(): DocumentFactory
    {
        return DocumentFactory::new();
    }

    /**
     * Resolve the table name from the package configuration.
     */
    public function getTable(): string
    {
        return config('persona.tables.documents', 'persona_documents');
    }

    protected function casts(): array
    {
        return [
            'number' => ConditionalEncrypted::class,
            'number_hash' => LookupHash::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function personable(): MorphTo
    {
        return $this->morphTo();
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        return $query->where('status', 'verified')
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isCurrentlyValid(): bool
    {
        return $this->isVerified() && ! $this->isExpired();
    }
}