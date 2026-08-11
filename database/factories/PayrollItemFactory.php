<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollItem>
 */
class PayrollItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'employee_id' => Employee::factory(),
            'basic_salary' => fake()->randomFloat(2, 15000, 80000),
            'gross_amount' => fake()->randomFloat(2, 15000, 80000),
            'total_deductions' => fake()->randomFloat(2, 3000, 15000),
            'net_amount' => fake()->randomFloat(2, 10000, 65000),
            'gsis_employee_share' => fake()->randomFloat(2, 1000, 7000),
            'philhealth_employee_share' => fake()->randomFloat(2, 250, 2500),
            'pagibig_employee_share' => fake()->randomFloat(2, 100, 200),
            'withholding_tax' => fake()->randomFloat(2, 0, 10000),
            'computation_json' => [],
        ];
    }
}
