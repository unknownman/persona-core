<?php

namespace Persona\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPhoneNumber implements ValidationRule
{
    /**
     * A basic E.164-style phone number check.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\+?[1-9]\d{1,14}$/', $value)) {
            $fail(__('The :attribute must be a valid phone number.'));
        }
    }
}