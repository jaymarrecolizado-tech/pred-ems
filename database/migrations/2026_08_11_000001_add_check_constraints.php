<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Adds DB-level CHECK constraints on critical status/enum columns.
 *
 * Application-level validation already enforces these values, but a DB-level
 * CHECK is the last line of defense against data corruption from direct SQL
 * access, bugs in batch operations, or future code changes.
 *
 * MySQL 8.0.16+ enforces CHECK constraints (earlier versions silently ignore them).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Employee status
        DB::statement("ALTER TABLE employees ADD CONSTRAINT chk_employees_status CHECK (status IN ('active', 'on_leave', 'separated', 'resigned', 'retired'))");

        // Gender
        DB::statement("ALTER TABLE employees ADD CONSTRAINT chk_employees_gender CHECK (gender IS NULL OR gender IN ('Male', 'Female'))");

        // Leave application status
        DB::statement("ALTER TABLE leave_applications ADD CONSTRAINT chk_leave_status CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))");

        // Attendance log punch types
        DB::statement("ALTER TABLE attendance_logs ADD CONSTRAINT chk_punch_type CHECK (punch_type IN ('am_in', 'am_out', 'pm_in', 'pm_out'))");

        // Attendance log source (geofence punch, HR manual entry, or a log
        // produced by an approved correction request)
        DB::statement("ALTER TABLE attendance_logs ADD CONSTRAINT chk_log_source CHECK (source IN ('geofence', 'hr_manual', 'hr_correction'))");

        // Attendance correction status
        DB::statement("ALTER TABLE attendance_corrections ADD CONSTRAINT chk_correction_status CHECK (status IN ('pending', 'approved', 'rejected'))");

        // Payroll period status
        DB::statement("ALTER TABLE payroll_periods ADD CONSTRAINT chk_payroll_status CHECK (status IN ('draft', 'finalized', 'paid', 'voided'))");

        // Remittance status
        DB::statement("ALTER TABLE remittances ADD CONSTRAINT chk_remittance_status CHECK (status IN ('pending', 'remitted', 'verified'))");

        // Document request status
        DB::statement("ALTER TABLE document_requests ADD CONSTRAINT chk_doc_request_status CHECK (status IN ('pending', 'issued', 'rejected', 'canceled'))");

        // Holiday type
        DB::statement("ALTER TABLE holidays ADD CONSTRAINT chk_holiday_type CHECK (type IN ('regular_holiday', 'special_nonworking', 'work_suspension'))");

        // SMS queue status
        DB::statement("ALTER TABLE sms_queue ADD CONSTRAINT chk_sms_status CHECK (status IN ('pending', 'sent', 'failed'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE employees DROP CONSTRAINT chk_employees_status');
        DB::statement('ALTER TABLE employees DROP CONSTRAINT chk_employees_gender');
        DB::statement('ALTER TABLE leave_applications DROP CONSTRAINT chk_leave_status');
        DB::statement('ALTER TABLE attendance_logs DROP CONSTRAINT chk_punch_type');
        DB::statement('ALTER TABLE attendance_logs DROP CONSTRAINT chk_log_source');
        DB::statement('ALTER TABLE attendance_corrections DROP CONSTRAINT chk_correction_status');
        DB::statement('ALTER TABLE payroll_periods DROP CONSTRAINT chk_payroll_status');
        DB::statement('ALTER TABLE remittances DROP CONSTRAINT chk_remittance_status');
        DB::statement('ALTER TABLE document_requests DROP CONSTRAINT chk_doc_request_status');
        DB::statement('ALTER TABLE holidays DROP CONSTRAINT chk_holiday_type');
        DB::statement('ALTER TABLE sms_queue DROP CONSTRAINT chk_sms_status');
    }
};
