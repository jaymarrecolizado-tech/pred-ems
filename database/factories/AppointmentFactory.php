<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'position_id' => Position::factory(),
            'appointment_type' => fake()->randomElement(['original', 'promotion', 'transfer', 're_appointment']),
            'appointment_status' => 'approved',
            'effective_from' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
        ];
    }
}
