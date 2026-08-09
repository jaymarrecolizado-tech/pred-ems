<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vacation leave monetization (CSC Omnibus Rules on Leave, MC 41 s. 1998
     * as amended): VL credits converted to cash at (monthly salary ÷ 22) per
     * day, subject to ≥ 10 days accumulated, retain ≥ 5 days, max 30 days per
     * year. Each processed monetization debits the append-only leave ledger
     * with a 'monetized' entry and mints a MO-YYYY-NNNN voucher.
     */
    public function up(): void
    {
        Schema::create('leave_monetizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('days', 5, 2);
            $table->decimal('per_day_rate', 12, 2);   // monthly salary ÷ 22
            $table->decimal('gross_amount', 12, 2);
            $table->string('reference_no', 40)->unique();
            $table->text('remarks')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_monetizations');
    }
};
