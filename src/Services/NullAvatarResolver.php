<?php

namespace Persona\Services;

use Persona\Contracts\AvatarResolverContract;
use Persona\Models\Profile;

class NullAvatarResolver implements AvatarResolverContract
{
    /**
     * Resolve the avatar URL for a given Profile.
     *
     * @param  \Persona\Models\Profile  $profile
     * @return string|null
     */
    public function getAvatarUrl(Profile $profile): ?string
    {
        return null;
    }
}
