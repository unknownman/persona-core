<?php

namespace Persona\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Persona\Models\Contact;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $type  = fake()->randomElement(['email', 'phone']);
        $value = $type === 'email' ? fake()->safeEmail() : fake()->e164PhoneNumber();

        return [
            'type'         => $type,
            'value'        => $value,
            'value_hash'   => $value,
            'is_primary'   => false,
            'is_verified'  => false,
            'verified_at'  => null,
            'is_emergency' => false,
        ];
    }

    public function email(): static
    {
        return $this->state(fn () => [
            'type'  => 'email',
            'value' => $value = fake()->safeEmail(),
            'value_hash' => $value,
        ]);
    }

    public function phone(): static
    {
        return $this->state(fn () => [
            'type'  => 'phone',
            'value' => $value = fake()->e164PhoneNumber(),
            'value_hash' => $value,
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'is_verified' => true,
            'verified_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function emergency(): static
    {
        return $this->state(fn () => ['is_emergency' => true]);
    }
}
