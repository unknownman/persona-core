<?php

namespace Persona\Normalizers;

use Persona\Contracts\EmailNormalizerContract;

class DefaultEmailNormalizer implements EmailNormalizerContract
{
    public function normalize(string $value): string
    {
        return strtolower(trim($value));
    }
}