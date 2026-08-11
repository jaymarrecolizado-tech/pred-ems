<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'date' => fake()->dateTimeBetween('-1 month', '+6 months')->format('Y-m-d'),
            'type' => fake()->randomElement(['regular_holiday', 'special_nonworking', 'work_suspension']),
            'is_repeating' => false,
        ];
    }
}
