<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Persona\Casts\ConditionalEncrypted;
use Persona\Database\Factories\ProfileFactory;
use Persona\Traits\BelongsToPersonable;

class Profile extends Model
{
    use BelongsToPersonable, HasFactory;

    protected $guarded = [];

    protected static function newFactory(): ProfileFactory
    {
        return ProfileFactory::new();
    }

    /**
     * Resolve the table name from the package configuration.
     */
    public function getTable(): string
    {
        return config('persona.tables.profiles', 'persona_profiles');
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => ConditionalEncrypted::class,
        ];
    }

    /**
     * Resolve the avatar URL via the bound AvatarResolverContract.
     *
     * Implemented strictly as an Eloquent Attribute (never a raw column) so
     * `avatar_url` flows through `toArray()` / JSON serialization only when
     * explicitly appended — callers can safely persist the model without a
     * missing-column SQLSTATE, and serializing profile lists does not hammer
     * the external avatar resolver. `shouldCache()` keeps the resolver call
     * at one per model instance.
     *
     * Consumers that need the value in a serialized form must append it
     * explicitly (e.g. `$profile->append('avatar_url')`).
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(
            fn () => app(\Persona\Contracts\AvatarResolverContract::class)->getAvatarUrl($this)
        )->shouldCache();
    }
}