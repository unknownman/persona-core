<?php

namespace Persona\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Persona\Models\Document;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $number = strtoupper(fake()->bothify('??####-####'));

        return [
            'type'         => 'passport',
            'number'       => $number,
            'number_hash'  => $number,
            'country_code' => fake()->countryCode(),
            'issued_at'    => fake()->dateTimeBetween('-10 years', '-1 year'),
            'expires_at'   => fake()->dateTimeBetween('+1 year', '+10 years'),
            'status'       => 'pending',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['status' => 'verified']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => fake()->dateTimeBetween('-2 years', '-1 day'),
            'status'     => 'verified',
        ]);
    }
}
