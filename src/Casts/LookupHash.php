<?php

namespace Persona\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class LookupHash implements CastsAttributes
{
    /**
     * Transform the given value into the storage format.
     *
     * @return string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $hashKey = config('persona.hash_key');

        if (is_null($hashKey) || $hashKey === '') {
            throw new RuntimeException(
                'PERSONA_HASH_KEY is missing or empty. Please generate a unique key and set it in your .env file to secure sensitive Persona hashes.'
            );
        }

        return hash_hmac('sha256', (string) $value, $hashKey);
    }

    /**
     * Return the stored value for consumption.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}