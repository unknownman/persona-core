<?php

namespace Persona\Normalizers;

use Persona\Contracts\PhoneNormalizerContract;

class DefaultPhoneNormalizer implements PhoneNormalizerContract
{
    public function normalize(string $value): string
    {
        $value = trim($value);

        if (str_starts_with($value, '+')) {
            return '+' . preg_replace('/\D+/', '', $value);
        }

        return preg_replace('/\D+/', '', $value);
    }
}