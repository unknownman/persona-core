<?php

namespace Persona\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Persona\Models\Profile;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'first_name'  => fake()->firstName(),
            'last_name'   => fake()->lastName(),
            'middle_name' => fake()->optional(0.3)->firstName(),
            'gender'      => fake()->randomElement(['male', 'female', 'non_binary', 'other', 'unspecified']),
            'birth_date'  => fake()->dateTimeBetween('-80 years', '-18 years'),
            'locale'      => fake()->randomElement(['en', 'de', 'fr', 'es', 'pt', 'it', 'nl', 'ja', 'zh']),
            'timezone'    => fake()->timezone(),
        ];
    }
}
