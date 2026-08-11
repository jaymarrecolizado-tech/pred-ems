<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['admin', 'hr', 'payroll', 'employee', 'unit_head']),
            'label' => fake()->words(2, true),
        ];
    }
}
