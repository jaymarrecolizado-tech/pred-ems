<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceLog>
 */
class AttendanceLogFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'employee_id' => Employee::factory(),
            'log_date' => $date->format('Y-m-d'),
            'punch_type' => fake()->randomElement(['am_in', 'am_out', 'pm_in', 'pm_out']),
            'punched_at' => $date->format('Y-m-d H:i:s'),
            'source' => 'geofence',
        ];
    }
}
