<?php

namespace Persona\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidNationalId implements ValidationRule
{
    /**
     * A generic alphanumeric national identifier check.
     *
     * Country-specific formats (SSN, Iranian national code, etc.) are
     * intentionally NOT encoded here; extend this rule per country in the
     * host application when stricter validation is required.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[A-Za-z0-9]{4,32}$/', $value)) {
            $fail('The :attribute must be a valid national ID.');
        }
    }
}