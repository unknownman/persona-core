<?php

namespace Persona\Normalizers;

use Persona\Contracts\HandleNormalizerContract;

class DefaultHandleNormalizer implements HandleNormalizerContract
{
    public function normalize(string $value): string
    {
        $value = trim($value);

        if (preg_match('#^(?:https?://)?(?:www\.)?[^/\s]+/(.+)$#i', $value, $matches)) {
            $value = $matches[1];
        }

        return ltrim($value, '@');
    }
}