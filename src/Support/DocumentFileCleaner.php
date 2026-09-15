<?php

namespace Persona\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Single home for the "remove a document's physical files from storage" logic.
 *
 * Every caller that needs to delete physical document files — soft-deleting a
 * Document, Eloquent pruning, or wiping an entity's whole Persona footprint —
 * delegates here so future changes (logging, retries) land in exactly one place.
 */
class DocumentFileCleaner
{
    /**
     * Delete every physical file described by the given collection.
     *
     * Accepts a Collection of \Persona\Models\DocumentFile models (e.g.
     * `$document->files`) or a Collection of stdClass rows shaped like
     * `{disk, file_path}` as produced by raw query-builder reads. Both expose
     * the same two properties, so a single signature covers both shapes.
     *
     * Storage failures are intentionally swallowed so cleanup never aborts
     * the surrounding database operation.
     */
    public static function deleteFilesFor(Collection $files): void
    {
        foreach ($files as $file) {
            Storage::disk($file->disk)->delete($file->file_path);
        }
    }
}