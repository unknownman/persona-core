<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Persona\Casts\ConditionalEncrypted;
use Persona\Casts\LookupHash;
use Persona\Database\Factories\DocumentFactory;
use Persona\Support\DocumentFileCleaner;
use Persona\Traits\BelongsToPersonable;

class Document extends Model
{
    use BelongsToPersonable, HasFactory, Prunable, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['number_hash'];

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

    protected function pruning(): void
    {
        DocumentFileCleaner::deleteFilesFor($this->files);
    }

    public function prunable(): Builder
    {
        $days = config('persona.retention.soft_deleted_days', 30);

        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays($days))
            ->with('files');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    /**
     * Resolve a document status label from the package configuration.
     *
     * Hosts may rename the status vocabulary via `persona.document_statuses.*`,
     * so status comparisons must always read from config rather than hardcode.
     */
    protected static function statusValue(string $key): string
    {
        return (string) config('persona.document_statuses.' . $key, $key);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', static::statusValue('initial'));
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', static::statusValue('rejected'));
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        return $query->where('status', static::statusValue('verified'))
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function isVerified(): bool
    {
        return $this->status === static::statusValue('verified');
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