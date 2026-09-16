<?php

namespace Persona\Support;

use RuntimeException;
use Persona\Persona;

/**
 * Single source of truth for Persona's hashed lookup columns.
 *
 * Every hashed value in the package (contact `value_hash`, document
 * `number_hash`, legal `tax_id_hash`, ...) is an HMAC-SHA256 digest of the
 * raw value keyed by `persona.hash_key` (falling back to the application
 * key). The `LookupHash` cast, the uniqueness rule, and the managers all
 * delegate here so the algorithm and failure mode can never drift apart.
 */
class PersonaHasher
{
    public static function hash(string $value): string
    {
        if (isset(Persona::$hashCallback)) {
            return call_user_func(Persona::$hashCallback, $value);
        }

        $key = config('persona.hash_key') ?: config('app.key');

        if (is_null($key) || $key === '') {
            throw new RuntimeException(
                'No application or persona hash key has been specified. Please ensure APP_KEY or PERSONA_HASH_KEY is set in your .env file.'
            );
        }

        return hash_hmac('sha256', $value, $key);
    }
}