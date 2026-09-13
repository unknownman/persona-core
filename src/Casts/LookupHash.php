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

        $hashKey = config('persona.hash_key') ?: config('app.key');

        if (is_null($hashKey) || $hashKey === '') {
            throw new RuntimeException(
                'No application or persona hash key has been specified. Please ensure APP_KEY or PERSONA_HASH_KEY is set in your .env file.'
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