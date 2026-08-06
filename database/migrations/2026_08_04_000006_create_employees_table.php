<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee 201-file profile.
     * Current employment snapshot (type/position/grade/salary) is denormalized
     * here for fast list queries; the authoritative history lives in `appointments`.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Name
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable(); // Jr., Sr., III

            // Personal
            $table->date('birth_date')->nullable();
            $table->string('birth_place', 150)->nullable();
            $table->string('gender', 20)->nullable();            // Male / Female
            $table->string('civil_status', 20)->nullable();      // Single / Married / Widowed / Separated
            $table->string('citizenship', 50)->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->string('residential_address', 255)->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('personal_email', 150)->nullable();

            // Government identifiers
            $table->string('gsis_no', 30)->nullable();
            $table->string('philhealth_no', 30)->nullable();
            $table->string('pagibig_no', 30)->nullable();
            $table->string('tin_no', 30)->nullable();
            $table->string('sss_no', 30)->nullable();

            // Employment snapshot
            $table->foreignId('employment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->unsignedTinyInteger('step')->nullable();
            $table->decimal('monthly_salary', 12, 2)->nullable();

            $table->date('date_original_appointment')->nullable();
            $table->date('date_last_promotion')->nullable();

            $table->string('status', 20)->default('active'); // active, on_leave, separated, resigned, retired
            $table->string('profile_photo_path', 255)->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            // Common lookup indexes
            $table->index(['last_name', 'first_name']);
            $table->index('status');
            $table->index('employment_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
