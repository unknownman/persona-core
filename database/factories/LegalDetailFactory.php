<?php

namespace Persona\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Persona\Models\LegalDetail;

class LegalDetailFactory extends Factory
{
    protected $model = LegalDetail::class;

    public function definition(): array
    {
        $taxId = fake()->numerify('###########');

        return [
            'nationality'   => fake()->countryCode(),
            'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed', 'separated']),
            'tax_id'        => $taxId,
            'tax_id_hash'   => $taxId,
        ];
    }
}
