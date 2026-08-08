<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 — Attendance & DTR.
     *
     * attendance_checkpoints : geofenced zones admin/HR plot on the map. A
     *   punch is only accepted when the client's GPS position is within the
     *   radius of an active checkpoint.
     * attendance_logs        : AM/PM in/out punches (one per employee/day/
     *   punch_type). Append-only from the client; alterations flow through
     *   the correction-request queue (never edited in place).
     * attendance_corrections : employee-requested alterations to a punch;
     *   HR approves (applies a new corrected entry) or rejects.
     * settings               : key/value app settings (office hours, etc.).
     */
    public function up(): void
    {
        Schema::create('attendance_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('address', 255)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(200);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->string('punch_type', 10); // am_in, am_out, pm_in, pm_out
            $table->timestamp('punched_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('checkpoint_id')->nullable()->constrained('attendance_checkpoints')->nullOnDelete();
            $table->string('source', 20)->default('geofence'); // geofence, hr_manual
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'log_date', 'punch_type']);
            $table->index(['employee_id', 'log_date']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->string('punch_type', 10); // am_in, am_out, pm_in, pm_out
            $table->time('requested_time');
            $table->string('reason', 500);
            $table->string('status', 15)->default('pending'); // pending, approved, rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('denial_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('attendance_checkpoints');
        Schema::dropIfExists('settings');
    }
};
