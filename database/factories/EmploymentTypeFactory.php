<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmploymentType>
 */
class EmploymentTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Permanent', 'Casual', 'Contract of Service', 'Job Order', 'GIP']),
            'code' => fake()->unique()->lexify('???'),
            'has_leave_credits' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
