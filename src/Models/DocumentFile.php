<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFile extends Model
{
    protected $guarded = [];

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