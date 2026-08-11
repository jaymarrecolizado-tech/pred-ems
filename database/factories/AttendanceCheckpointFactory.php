<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceCheckpoint>
 */
class AttendanceCheckpointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Office',
            'latitude' => fake()->latitude(16, 18),
            'longitude' => fake()->longitude(121, 123),
            'radius_meters' => 200,
            'is_active' => true,
        ];
    }
}
