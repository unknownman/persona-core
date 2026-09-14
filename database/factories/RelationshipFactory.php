<?php

namespace Persona\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Persona\Models\Relationship;

class RelationshipFactory extends Factory
{
    protected $model = Relationship::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement([
                'spouse', 'sibling', 'friend', 'partner', 'relative', 'colleague',
                'parent', 'child', 'guardian', 'dependent', 'employer', 'employee',
            ]),
        ];
    }

    public function directed(): static
    {
        return $this->state(fn () => [
            'type' => fake()->randomElement(['parent', 'child', 'guardian', 'dependent', 'employer', 'employee']),
        ]);
    }

    public function symmetric(): static
    {
        return $this->state(fn () => [
            'type' => fake()->randomElement(['spouse', 'sibling', 'friend', 'partner', 'relative', 'colleague']),
        ]);
    }
}
