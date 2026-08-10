<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes on columns used frequently for search, filtering, and joins
 * to improve query performance at production data volume.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index('employee_number');
            $table->index('last_name');
            $table->index('first_name');
            $table->index('status');
            $table->index('employment_type_id');
            $table->index('division_id');
            $table->index('source_of_fund');
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->index('status');
            $table->index(['employee_id', 'date_from']);
            $table->index('leave_type_id');
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->index(['employee_id', 'log_date']);
        });

        Schema::table('leave_credit_ledger', function (Blueprint $table) {
            $table->index(['employee_id', 'leave_type_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index('reference_no');
            $table->index('employee_id');
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->index(['employee_id', 'status']);
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->index('payroll_period_id');
            $table->index('employee_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['employee_number']);
            $table->dropIndex(['last_name']);
            $table->dropIndex(['first_name']);
            $table->dropIndex(['status']);
            $table->dropIndex(['employment_type_id']);
            $table->dropIndex(['division_id']);
            $table->dropIndex(['source_of_fund']);
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['employee_id', 'date_from']);
            $table->dropIndex(['leave_type_id']);
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'log_date']);
        });

        Schema::table('leave_credit_ledger', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'leave_type_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['reference_no']);
            $table->dropIndex(['employee_id']);
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'status']);
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropIndex(['payroll_period_id']);
            $table->dropIndex(['employee_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['model_type', 'model_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['action']);
        });
    }
};
