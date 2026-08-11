<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Position>
 */
class PositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'code' => fake()->unique()->bothify('POS-##??'),
        ];
    }
}
