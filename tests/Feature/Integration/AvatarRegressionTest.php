<?php

namespace Persona\Tests\Feature\Integration;

use Persona\Contracts\AvatarResolverContract;
use Persona\Models\Profile;
use Persona\Persona;
use Persona\Tests\TestCase;

class AvatarRegressionTest extends TestCase
{
    public function test_profile_update_does_not_persist_avatar_url_column(): void
    {
        $user = $this->createUser();

        $profile = Persona::for($user)->updateProfile(['first_name' => 'Ali']);

        $profile->update(['first_name' => 'Aria']);

        $this->assertSame('Aria', $profile->fresh()->first_name);
        $this->assertArrayNotHasKey('avatar_url', $profile->getAttributes());
    }

    public function test_avatar_url_is_only_appended_through_the_footprint(): void
    {
        $this->app->instance(AvatarResolverContract::class, new class implements AvatarResolverContract {
            public function getAvatarUrl(\Persona\Models\Profile $profile): ?string
            {
                return 'https://example.com/avatars/1.jpg';
            }
        });

        $user = $this->createUser();
        $profile = Persona::for($user)->updateProfile(['first_name' => 'Ali']);

        // The accessor is available on the model instance…
        $this->assertSame('https://example.com/avatars/1.jpg', $profile->avatar_url);

        // …but it is NOT auto-appended: serializing a profile in a list must
        // not trigger the external resolver for every row.
        $this->assertArrayNotHasKey('avatar_url', $profile->toArray());

        // The footprint explicitly appends it for serialized output.
        $footprintProfile = Persona::for($user)->getFootprint()['profile'];
        $this->assertSame('https://example.com/avatars/1.jpg', $footprintProfile->avatar_url);
        $this->assertArrayHasKey('avatar_url', $footprintProfile->toArray());
    }

    public function test_serializing_profile_lists_does_not_call_the_avatar_resolver(): void
    {
        $resolver = new class implements AvatarResolverContract {
            public static int $calls = 0;

            public function getAvatarUrl(\Persona\Models\Profile $profile): ?string
            {
                self::$calls++;

                return 'https://example.com/avatars/1.jpg';
            }
        };

        $this->app->instance(AvatarResolverContract::class, $resolver);

        $users = collect();
        foreach ([1, 2, 3] as $id) {
            $user = $this->createUser($id);
            $users->push($user);

            Persona::for($user)->updateProfile(['first_name' => "User {$id}"]);
        }

        // No appended attribute → serializing every profile resolves zero avatars.
        Profile::all()->each->toArray();
        $this->assertSame(0, $resolver::$calls);

        // A single footprint append costs exactly one resolver invocation.
        $profile = Persona::for($users->first())->getFootprint()['profile'];
        $this->assertArrayHasKey('avatar_url', $profile->toArray());
        $this->assertSame(1, $resolver::$calls);
    }

    public function test_avatar_url_is_null_with_default_null_resolver(): void
    {
        $user = $this->createUser();
        $profile = Persona::for($user)->updateProfile(['first_name' => 'Ali']);

        $this->assertNull($profile->avatar_url);
    }
}