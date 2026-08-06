<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monthly contribution remittance tracking (GSIS, PhilHealth, PAG-IBIG, BIR).
     * Summaries can be derived from payroll_items; this table tracks the actual
     * remittance lifecycle per agency.
     */
    public function up(): void
    {
        Schema::create('remittances', function (Blueprint $table) {
            $table->id();
            $table->string('agency', 20);          // GSIS, PHILHEALTH, PAGIBIG, BIR
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('employee_share_total', 14, 2)->default(0);
            $table->decimal('employer_share_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending, remitted, verified
            $table->string('reference_no', 60)->nullable();
            $table->timestamp('remitted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['agency', 'period_from', 'period_to'], 'remittance_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittances');
    }
};
