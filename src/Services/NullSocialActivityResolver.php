<?php

namespace Persona\Services;

use Persona\Contracts\SocialActivityResolverContract;
use Persona\Models\SocialAccount;

/**
 * Default resolver that returns no activity.
 *
 * Swap this with a real implementation via `AppServiceProvider::register()`:
 *
 *     $this->app->singleton(
 *         SocialActivityResolverContract::class,
 *         TwitterActivityResolver::class,
 *     );
 */
final class NullSocialActivityResolver implements SocialActivityResolverContract
{
    /**
     * @return list<array{url: string, published_at?: string, text?: string}>
     */
    public function getRecentActivity(SocialAccount $account): array
    {
        return [];
    }
}