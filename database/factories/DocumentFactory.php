<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'document_type' => fake()->randomElement(['coe', 'service_record', 'leave_balances', 'no_pending_case', 'dtr']),
            'reference_no' => fake()->unique()->bothify('??-####'),
            'remarks' => fake()->optional()->sentence(),
            'generated_at' => now(),
        ];
    }
}
