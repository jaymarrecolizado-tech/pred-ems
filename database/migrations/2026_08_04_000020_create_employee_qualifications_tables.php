<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Full 201-file qualification records: education, work experience,
     * and CSC civil service eligibility (very Philippine-specific).
     */
    public function up(): void
    {
        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('level', 30);            // Elementary, Secondary, Vocational, College, Graduate Studies
            $table->string('school_name', 150);
            $table->string('course', 150)->nullable();
            $table->year('year_graduated')->nullable();
            $table->string('honors', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('employee_work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('position_title', 150);
            $table->string('company', 150);
            $table->string('monthly_salary', 50)->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('appointment_status', 50)->nullable(); // Permanent, Contractual, Casual...
            $table->boolean('is_government_service')->default(false);
            $table->timestamps();
        });

        Schema::create('employee_civil_service_eligibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('eligibility', 150);     // e.g. CS Professional, CS Subprofessional
            $table->string('rating', 20)->nullable();
            $table->date('date_examined')->nullable();
            $table->string('place_examined', 100)->nullable();
            $table->string('license_number', 50)->nullable();
            $table->date('validity_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_civil_service_eligibilities');
        Schema::dropIfExists('employee_work_experiences');
        Schema::dropIfExists('employee_educations');
    }
};
