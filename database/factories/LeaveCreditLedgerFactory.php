<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveCreditLedger>
 */
class LeaveCreditLedgerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'credit' => 1.25,
            'debit' => 0,
            'source_type' => 'monthly_accrual',
            'reference' => fake()->optional()->sentence(),
        ];
    }
}
