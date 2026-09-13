<?php

namespace Persona\Contracts;

interface PhoneNormalizerContract
{
    /**
     * Normalize a phone number to a canonical E.164-style form.
     */
    public function normalize(string $value): string;
}