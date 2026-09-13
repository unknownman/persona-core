<?php

namespace Persona\Services;

use Illuminate\Database\Eloquent\Model;
use Persona\Contracts\DocumentVerificationProvider;

class NullDocumentVerificationProvider implements DocumentVerificationProvider
{
    /**
     * The default provider performs no external verification.
     *
     * Documents therefore require manual review before they can be
     * considered verified. Host applications should replace this
     * binding with a real KYC-backed implementation.
     */
    public function verify(Model $document): bool
    {
        return false;
    }
}