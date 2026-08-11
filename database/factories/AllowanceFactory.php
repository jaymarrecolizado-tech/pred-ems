<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Allowance>
 */
class AllowanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('????'),
            'name' => fake()->words(2, true),
            'computation' => 'fixed',
            'amount' => fake()->randomFloat(2, 500, 5000),
            'is_taxable' => false,
            'is_active' => true,
        ];
    }
}
