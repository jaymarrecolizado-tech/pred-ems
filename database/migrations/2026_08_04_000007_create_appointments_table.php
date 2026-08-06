<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chronological appointment history. THE source of truth for the
     * auto-generated CSC Service Record.
     *
     * Effective-dated (effective_from/effective_to) so retroactive
     * promotions never corrupt past payroll periods.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained()->nullOnDelete();

            $table->string('appointment_type', 40); // original, promotion, transfer, demotion, re_appointment, co_terminus, job_order, contract_of_service, gip, casual, temporary
            $table->string('appointment_status', 20)->default('approved'); // approved, pending, revoked

            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->unsignedTinyInteger('step')->nullable();
            $table->decimal('monthly_salary', 12, 2)->nullable();

            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // null = current
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
