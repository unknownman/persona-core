<?php

namespace Persona\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPersonaConfigValue implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  string  $configKey  A dotted config key pointing to a list of
     *                             allowed values, e.g. 'persona.document_types'
     *                             or 'persona.social_platforms'.
     */
    public function __construct(protected string $configKey) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $allowed = config($this->configKey, []);

        if (! is_array($allowed)) {
            return;
        }

        $allowedValues = array_is_list($allowed)
            ? $allowed
            : array_keys($allowed);

        if (! in_array($value, $allowedValues, true)) {
            $fail("The :attribute must be one of the values defined in {$this->configKey}.");
        }
    }
}