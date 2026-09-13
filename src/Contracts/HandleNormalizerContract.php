<?php

namespace Persona\Contracts;

interface HandleNormalizerContract
{
    /**
     * Normalize a social handle to a bare username form.
     */
    public function normalize(string $value): string;
}