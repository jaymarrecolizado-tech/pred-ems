<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll run periods. A period moves draft -> finalized -> paid;
     * finalized periods are locked against edits (correct via reversal runs).
     */
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);            // e.g. "August 2026"
            $table->date('period_from');
            $table->date('period_to');
            $table->date('payroll_date');
            $table->string('status', 20)->default('draft'); // draft, finalized, paid, voided
            $table->text('remarks')->nullable();

            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['period_from', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
