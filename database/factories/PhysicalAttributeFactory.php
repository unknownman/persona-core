<?php

namespace Persona\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Persona\Models\PhysicalAttribute;

class PhysicalAttributeFactory extends Factory
{
    protected $model = PhysicalAttribute::class;

    public function definition(): array
    {
        return [
            'height'     => fake()->numberBetween(140, 210),
            'weight'     => fake()->numberBetween(45, 130),
            'eye_color'  => fake()->randomElement(['brown', 'blue', 'green', 'hazel', 'gray']),
            'hair_color' => fake()->randomElement(['black', 'brown', 'blonde', 'red', 'gray', 'white']),
            'blood_type' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
        ];
    }
}
