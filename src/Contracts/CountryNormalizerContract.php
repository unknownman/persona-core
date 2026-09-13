<?php

namespace Persona\Contracts;

interface CountryNormalizerContract
{
    /**
     * Normalize a country code to its ISO 3166-1 alpha-2 form.
     */
    public function normalize(string $value): string;
}