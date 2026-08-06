<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only ledger of leave credit movements.
     * Balance is always derived from this ledger — never stored on the employee,
     * so history is immutable and corrections are new entries.
     */
    public function up(): void
    {
        Schema::create('leave_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();

            $table->date('transaction_date');
            $table->string('movement', 20);        // accrual, used, monetized, adjustment, opening_balance
            $table->decimal('credit', 6, 2)->default(0); // + days
            $table->decimal('debit', 6, 2)->default(0);  // - days
            $table->decimal('balance_after', 6, 2);

            $table->unsignedBigInteger('source_id')->nullable();   // leave_application_id / payroll_id
            $table->string('source_type', 40)->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'leave_type_id', 'transaction_date'], 'ledger_emp_leave_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_credit_ledger');
    }
};
