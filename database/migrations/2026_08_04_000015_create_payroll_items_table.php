<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-employee payroll computation for a period.
     * computation_json retains the FULL calculation trace (salary, rates used,
     * bracket applied) so every payslip is auditable years later.
     */
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Income
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('pera', 12, 2)->default(0);
            $table->decimal('honoraria', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('other_income', 12, 2)->default(0);
            $table->json('income_json')->nullable();       // additional income lines

            // Deductions
            $table->decimal('gsis_employee_share', 12, 2)->default(0);
            $table->decimal('philhealth_employee_share', 12, 2)->default(0);
            $table->decimal('pagibig_employee_share', 12, 2)->default(0);
            $table->decimal('withholding_tax', 12, 2)->default(0);
            $table->decimal('lwop_deduction', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->json('deduction_json')->nullable();    // additional deduction lines

            $table->decimal('gross_amount', 12, 2);
            $table->decimal('total_deductions', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->json('computation_json')->nullable();  // full audit trace

            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id'], 'payroll_period_employee_unique');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
