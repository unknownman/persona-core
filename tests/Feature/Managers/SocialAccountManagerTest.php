<?php

namespace Persona\Tests\Feature\Managers;

use Illuminate\Support\Facades\Event;
use Persona\Events\SocialAccountConnected;
use Persona\Events\SocialAccountMadePrimary;
use Persona\Events\SocialAccountRemoved;
use Persona\Persona;
use Persona\Tests\TestCase;

class SocialAccountManagerTest extends TestCase
{
    public function test_add_social_account_dispatches_connected_once(): void
    {
        Event::fake();

        $user = $this->createUser();
        $account = Persona::for($user)->addSocialAccount('github', 'octocat', 'https://github.com/octocat');

        Event::assertDispatchedTimes(SocialAccountConnected::class, 1);
        Event::assertDispatched(SocialAccountConnected::class, function (SocialAccountConnected $event) use ($account, $user) {
            return $event->account->is($account)
                && $event->account->platform === 'github'
                && $event->account->personable_id == $user->getKey();
        });
    }

    public function test_make_primary_dispatches_social_account_made_primary_once(): void
    {
        Event::fake();

        $user = $this->createUser();
        $account = Persona::for($user)->addSocialAccount('github', 'octocat');

        Persona::for($user)->makeSocialAccountPrimary($account);

        Event::assertDispatchedTimes(SocialAccountMadePrimary::class, 1);
        Event::assertDispatched(SocialAccountMadePrimary::class, function (SocialAccountMadePrimary $event) use ($account) {
            return $event->account->is($account) && $event->account->is_primary === true;
        });
    }

    public function test_delete_social_account_dispatches_removed_once(): void
    {
        Event::fake();

        $user = $this->createUser();
        $account = Persona::for($user)->addSocialAccount('github', 'octocat');

        $deleted = Persona::for($user)->deleteSocialAccount($account);

        $this->assertTrue($deleted);
        Event::assertDispatchedTimes(SocialAccountRemoved::class, 1);
        Event::assertDispatched(SocialAccountRemoved::class, function (SocialAccountRemoved $event) use ($account) {
            return $event->account->is($account);
        });
    }
}