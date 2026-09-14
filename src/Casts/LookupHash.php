<?php

namespace Persona\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Persona\Support\PersonaHasher;

class LookupHash implements CastsAttributes
{
    /**
     * Transform the given value into the storage format.
     *
     * Delegates to PersonaHasher — the single source of truth for the
     * hashing algorithm.
     *
     * @return string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return PersonaHasher::hash((string) $value);
    }

    /**
     * Return the stored value for consumption.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}