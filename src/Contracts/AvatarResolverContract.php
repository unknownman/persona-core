<?php

namespace Persona\Contracts;

use Persona\Models\Profile;

interface AvatarResolverContract
{
    /**
     * Resolve the avatar URL for a given Profile.
     *
     * @param  \Persona\Models\Profile  $profile
     * @return string|null
     */
    public function getAvatarUrl(Profile $profile): ?string;
}
