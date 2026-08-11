<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomElement(['VL', 'SL', 'SLP', 'MATERNITY', 'PATERNITY', 'SOLO_PARENT', 'VAWC', 'STUDY']),
            'name' => fake()->words(2, true),
            'accrual_per_month' => 1.25,
            'annual_max_credit' => 15,
            'is_cumulative' => true,
            'is_commutable' => true,
            'requires_approval' => true,
            'is_active' => true,
        ];
    }
}
