<?php

namespace Persona\Contracts;

use Illuminate\Database\Eloquent\Model;

interface DocumentVerificationProvider
{
    /**
     * Verify a Persona document against an external truth source.
     *
     * The $document is expected to be a \Persona\Models\Document instance,
     * but it is type-hinted against the base Eloquent model to avoid tight
     * coupling and circular dependencies.
     *
     * @return bool True when the document is verified externally.
     */
    public function verify(Model $document): bool;
}