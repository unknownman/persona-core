<?php

namespace Persona\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Persona\Models\Address;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'type'         => fake()->randomElement(['home', 'work', 'billing', 'shipping']),
            'line_1'       => fake()->streetAddress(),
            'line_2'       => fake()->optional(0.3)->secondaryAddress(),
            'city'         => fake()->city(),
            'state'        => fake()->stateAbbr(),
            'country_code' => fake()->countryCode(),
            'zip_code'     => fake()->postcode(),
            'is_primary'   => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
