<?php

namespace Persona\Contracts;

use Illuminate\Http\UploadedFile;
use Persona\Models\Document;

interface DocumentPathGeneratorContract
{
    /**
     * Generate the file path where a document's uploaded file should be stored.
     *
     * @param  \Persona\Models\Document  $document
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return string
     */
    public function generate(Document $document, UploadedFile $file): string;
}
