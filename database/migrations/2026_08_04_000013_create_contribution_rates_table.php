<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Config-driven contribution & tax rules (GSIS, PhilHealth, PAG-IBIG, BIR).
     * The `config` JSON holds structured parameters:
     *   GSIS       -> { employee_rate: 9, employer_rate: 12 }
     *   PHILHEALTH -> { rate: 5, floor: 10000, ceiling: 100000, min_premium: 500, max_premium: 5000 }
     *   PAGIBIG    -> { low_bracket: 1500, low_employee: 1, low_employer: 2, std_employee: 2, std_employer: 2,
     *                  cap_base: 10000, cap_employee: 200, cap_employer: 200 }
     *   BIR        -> { exemption: 250000, brackets: [ {min, max, base_tax, rate} ] }
     * Annual updates are data changes, not code changes.
     */
    public function up(): void
    {
        Schema::create('contribution_rates', function (Blueprint $table) {
            $table->id();
            $table->string('agency', 20);        // GSIS, PHILHEALTH, PAGIBIG, BIR, OTHER
            $table->string('name', 100);         // e.g. "BIR Withholding Tax (TRAIN)"
            $table->json('config');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['agency', 'effective_from', 'effective_to']);
            $table->unique(['agency', 'name', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_rates');
    }
};
