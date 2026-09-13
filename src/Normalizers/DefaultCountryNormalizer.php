<?php

namespace Persona\Normalizers;

use Persona\Contracts\CountryNormalizerContract;

class DefaultCountryNormalizer implements CountryNormalizerContract
{
    public function normalize(string $value): string
    {
        return strtoupper(trim($value));
    }
}