<?php

namespace Persona\Contracts;

use Persona\Models\SocialAccount;

/**
 * Resolve recent public activity for a connected social account.
 *
 * Host applications may bind a custom implementation to fetch real-time
 * feeds from third-party APIs (Twitter, GitHub, etc.) without touching
 * the Persona UI layer.  The default binding is {@see \Persona\Services\NullSocialActivityResolver}.
 */
interface SocialActivityResolverContract
{
    /**
     * Retrieve the most recent public activity items for the given account.
     *
     * Each item should contain at minimum a `url` key pointing to the
     * external resource, with optional `text` and `published_at` keys.
     *
     * @return list<array{url: string, published_at?: string, text?: string}>
     */
    public function getRecentActivity(SocialAccount $account): array;
}