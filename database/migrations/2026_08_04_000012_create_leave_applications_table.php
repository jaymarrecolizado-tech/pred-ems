<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leave filing and approval workflow.
     */
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();

            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('days_applied', 5, 2);
            $table->string('reason', 255);
            $table->string('contact_during_leave', 100)->nullable();

            $table->string('status', 20)->default('pending'); // pending, approved, rejected, cancelled
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('denial_reason', 255)->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'status', 'date_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
