<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Division>
 */
class DivisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => fake()->unique()->lexify('???'),
        ];
    }
}
