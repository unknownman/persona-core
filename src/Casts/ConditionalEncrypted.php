<?php

namespace Persona\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ConditionalEncrypted implements CastsAttributes
{
    /**
     * Decrypt the given stored value when encryption is enabled.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return $this->shouldEncrypt() ? Crypt::decrypt($value) : $value;
    }

    /**
     * Encrypt the given value when encryption is enabled.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return $this->shouldEncrypt() ? Crypt::encrypt($value) : $value;
    }

    /**
     * Determine whether sensitive data should be encrypted at rest.
     */
    protected function shouldEncrypt(): bool
    {
        return (bool) config('persona.encrypt_sensitive_data', true);
    }
}