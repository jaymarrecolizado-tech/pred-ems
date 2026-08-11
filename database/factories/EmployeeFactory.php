<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmploymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(['Male', 'Female']);

        return [
            'employee_number' => 'RO2-' . str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'first_name' => fake()->firstName($gender),
            'middle_name' => fake()->lastName(),
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'birth_date' => fake()->dateTimeBetween('-60 years', '-22 years')->format('Y-m-d'),
            'gender' => $gender,
            'civil_status' => fake()->randomElement(['Single', 'Married', 'Widowed']),
            'citizenship' => 'Filipino',
            'contact_number' => '09' . fake()->numberBetween(10, 99) . '-' . fake()->numberBetween(100, 999) . '-' . fake()->numberBetween(1000, 9999),
            'personal_email' => fake()->safeEmail(),
            'gov_email' => fake()->userName() . '@dict.gov.ph',
            'employment_type_id' => EmploymentType::factory(),
            'salary_grade' => fake()->numberBetween(1, 33),
            'step' => fake()->numberBetween(1, 8),
            'monthly_salary' => fake()->randomFloat(2, 15000, 80000),
            'date_original_appointment' => fake()->dateTimeBetween('-20 years', 'now')->format('Y-m-d'),
            'status' => 'active',
        ];
    }
}
