<?php

namespace Persona\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Persona\Models\SocialAccount;

class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    public function definition(): array
    {
        $platform = fake()->randomElement(['twitter', 'linkedin', 'github', 'instagram', 'facebook']);

        return [
            'platform'   => $platform,
            'username'   => fake()->userName(),
            'url'        => 'https://' . $platform . '.com/' . fake()->userName(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
