<?php

namespace Persona\Services;

use Illuminate\Http\UploadedFile;
use Persona\Contracts\DocumentPathGeneratorContract;
use Persona\Models\Document;

class DefaultDocumentPathGenerator implements DocumentPathGeneratorContract
{
    /**
     * Generate the default file path for a document.
     *
     * @param  \Persona\Models\Document  $document
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return string
     */
    public function generate(Document $document, UploadedFile $file): string
    {
        $basePath = trim(config('persona.storage.path', 'persona/documents'), '/');

        return $basePath . '/' . $document->id;
    }
}
