<?php

namespace Database\Factories;

use App\Models\Allowance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Allowance>
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
