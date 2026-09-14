<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Persona\Database\Factories\DocumentFileFactory;

class DocumentFile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): DocumentFileFactory
    {
        return DocumentFileFactory::new();
    }

    /**
     * Resolve the table name from the package configuration.
     */
    public function getTable(): string
    {
        return config('persona.tables.document_files', 'persona_document_files');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}