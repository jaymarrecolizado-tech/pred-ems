<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Official documents issued to/for employees:
     * Service Record, Certificate of Employment, Certificate of Leave Balances,
     * Certification of No Pending Case, payslip copies.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40); // service_record, certificate_of_employment, certificate_of_leave_balances, cert_no_pending_case, payslip_copy, other
            $table->string('reference_no', 40)->unique();
            $table->string('file_path', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
