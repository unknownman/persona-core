<?php

namespace Persona\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Persona\Events\SocialAccountConnected;
use Persona\Events\SocialAccountMadePrimary;
use Persona\Events\SocialAccountRemoved;
use Persona\Models\SocialAccount;

class SocialAccountManager
{
    /**
     * Attach a social account for a personable model.
     *
     * When $isPrimary is true the manager demotes every existing account on
     * the same $platform first, preserving the single-primary-per-platform
     * invariant inside a single DB transaction.
     *
     * @throws \InvalidArgumentException  When the platform is not allowed by the package config.
     */
    public function add(
        Model $personable,
        string $platform,
        string $username,
        ?string $url = null,
        bool $isPrimary = false,
    ): SocialAccount {
        $this->assertPlatform($platform);

        return DB::transaction(function () use ($personable, $platform, $username, $url, $isPrimary) {
            if ($isPrimary) {
                $this->demoteSamePlatform($personable, $platform);
            }

            $account = new SocialAccount($this->map(['url' => $url]));

            $account->platform = $platform;
            $account->username = $username;
            $account->is_primary = $isPrimary;
            $account->personable_type = $personable->getMorphClass();
            $account->personable_id = $personable->getKey();
            $account->save();

            // Dispatched inside the transaction, but `ShouldDispatchAfterCommit`
            // defers the actual dispatch until the transaction commits.
            SocialAccountConnected::dispatch($account);

            return $account;
        });
    }

    /**
     * Promote a social account to primary for its platform, preserving the
     * single-primary-per-platform invariant inside a transaction.
     *
     * @throws \InvalidArgumentException  When the account does not belong to the given entity.
     */
    public function makePrimary(Model $personable, SocialAccount $account): void
    {
        $this->assertOwnership($personable, $account);

        DB::transaction(function () use ($personable, $account) {
            $this->demoteSamePlatform($personable, (string) $account->platform);

            $account->update(['is_primary' => true]);

            SocialAccountMadePrimary::dispatch($account);
        });
    }

    /**
     * Delete a social account, verifying it belongs to the given entity first.
     *
     * @throws \InvalidArgumentException  When the account does not belong to the given entity.
     */
    public function delete(Model $personable, SocialAccount $account): bool
    {
        $this->assertOwnership($personable, $account);

        $deleted = (bool) $account->delete();

        if ($deleted) {
            SocialAccountRemoved::dispatch($account);
        }

        return $deleted;
    }

    /**
     * Demote every primary account of the given platform for the entity.
     */
    protected function demoteSamePlatform(Model $personable, string $platform): void
    {
        SocialAccount::query()
            ->where('personable_type', $personable->getMorphClass())
            ->where('personable_id', $personable->getKey())
            ->where('platform', $platform)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    /**
     * Assert that a platform is part of the configured allow-list.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertPlatform(string $platform): void
    {
        if (! in_array($platform, config('persona.social_platforms', []), true)) {
            throw new \InvalidArgumentException(
                "The social platform '{$platform}' is not allowed by the persona configuration."
            );
        }
    }

    /**
     * Assert that a SocialAccount is owned by the given personable entity.
     *
     * Must be called before ANY mutation that accepts an external
     * SocialAccount instance to prevent cross-entity IDOR attacks.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertOwnership(Model $personable, SocialAccount $account): void
    {
        if (
            $account->personable_type !== $personable->getMorphClass()
            || (string) $account->personable_id !== (string) $personable->getKey()
        ) {
            throw new \InvalidArgumentException(
                'The given social account does not belong to this entity.'
            );
        }
    }

    /**
     * Reduce the incoming attributes to the explicit whitelist, protecting
     * internal columns from raw array mass-assignment.
     *
     * The whitelist lives in `persona.fillable.social_account` so the host
     * application can extend it without touching this manager.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function map(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(config('persona.fillable.social_account', [])));
    }
}