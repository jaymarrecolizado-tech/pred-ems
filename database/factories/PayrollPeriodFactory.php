<?php

namespace Database\Factories;

use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    public function definition(): array
    {
        $from = fake()->dateTimeBetween('-2 months', 'now');
        $to = (clone $from)->modify('+14 days');

        return [
            'name' => $from->format('Y-m-d').' to '.$to->format('Y-m-d'),
            'period_from' => $from->format('Y-m-d'),
            'period_to' => $to->format('Y-m-d'),
            'payroll_date' => (clone $to)->modify('+5 days')->format('Y-m-d'),
            'status' => 'draft',
        ];
    }
}
