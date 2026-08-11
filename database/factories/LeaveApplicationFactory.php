<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveApplication>
 */
class LeaveApplicationFactory extends Factory
{
    public function definition(): array
    {
        $from = fake()->dateTimeBetween('now', '+30 days');
        $to = (clone $from)->modify('+1 day');

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'days_applied' => 1,
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
