<?php

namespace Persona\Contracts;

interface EmailNormalizerContract
{
    /**
     * Normalize an email address to a canonical form.
     */
    public function normalize(string $value): string;
}