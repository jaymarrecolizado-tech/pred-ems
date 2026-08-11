<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes on columns used frequently for search, filtering, and joins
 * to improve query performance at production data volume.
 *
 * Each index is guarded so the migration is safe to run on an existing
 * database whose earlier schema already carried some of these indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasIndex('employees', ['employee_number'])) {
                $table->index('employee_number');
            }
            if (! Schema::hasIndex('employees', ['last_name'])) {
                $table->index('last_name');
            }
            if (! Schema::hasIndex('employees', ['first_name'])) {
                $table->index('first_name');
            }
            if (! Schema::hasIndex('employees', ['status'])) {
                $table->index('status');
            }
            if (! Schema::hasIndex('employees', ['employment_type_id'])) {
                $table->index('employment_type_id');
            }
            if (! Schema::hasIndex('employees', ['division_id'])) {
                $table->index('division_id');
            }
            if (! Schema::hasIndex('employees', ['source_of_fund'])) {
                $table->index('source_of_fund');
            }
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            if (! Schema::hasIndex('leave_applications', ['status'])) {
                $table->index('status');
            }
            if (! Schema::hasIndex('leave_applications', ['employee_id', 'date_from'])) {
                $table->index(['employee_id', 'date_from']);
            }
            if (! Schema::hasIndex('leave_applications', ['leave_type_id'])) {
                $table->index('leave_type_id');
            }
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            if (! Schema::hasIndex('attendance_logs', ['employee_id', 'log_date'])) {
                $table->index(['employee_id', 'log_date']);
            }
        });

        Schema::table('leave_credit_ledger', function (Blueprint $table) {
            if (! Schema::hasIndex('leave_credit_ledger', ['employee_id', 'leave_type_id'])) {
                $table->index(['employee_id', 'leave_type_id']);
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasIndex('documents', ['reference_no'])) {
                $table->index('reference_no');
            }
            if (! Schema::hasIndex('documents', ['employee_id'])) {
                $table->index('employee_id');
            }
        });

        Schema::table('document_requests', function (Blueprint $table) {
            if (! Schema::hasIndex('document_requests', ['employee_id', 'status'])) {
                $table->index(['employee_id', 'status']);
            }
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            if (! Schema::hasIndex('payroll_items', ['payroll_period_id'])) {
                $table->index('payroll_period_id');
            }
            if (! Schema::hasIndex('payroll_items', ['employee_id'])) {
                $table->index('employee_id');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasIndex('audit_logs', ['model_type', 'model_id'])) {
                $table->index(['model_type', 'model_id']);
            }
            if (! Schema::hasIndex('audit_logs', ['user_id'])) {
                $table->index('user_id');
            }
            if (! Schema::hasIndex('audit_logs', ['action'])) {
                $table->index('action');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasIndex('employees', ['employee_number'])) {
                $table->dropIndex(['employee_number']);
            }
            if (Schema::hasIndex('employees', ['last_name'])) {
                $table->dropIndex(['last_name']);
            }
            if (Schema::hasIndex('employees', ['first_name'])) {
                $table->dropIndex(['first_name']);
            }
            if (Schema::hasIndex('employees', ['status'])) {
                $table->dropIndex(['status']);
            }
            if (Schema::hasIndex('employees', ['employment_type_id'])) {
                $table->dropIndex(['employment_type_id']);
            }
            if (Schema::hasIndex('employees', ['division_id'])) {
                $table->dropIndex(['division_id']);
            }
            if (Schema::hasIndex('employees', ['source_of_fund'])) {
                $table->dropIndex(['source_of_fund']);
            }
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            if (Schema::hasIndex('leave_applications', ['status'])) {
                $table->dropIndex(['status']);
            }
            if (Schema::hasIndex('leave_applications', ['employee_id', 'date_from'])) {
                $table->dropIndex(['employee_id', 'date_from']);
            }
            if (Schema::hasIndex('leave_applications', ['leave_type_id'])) {
                $table->dropIndex(['leave_type_id']);
            }
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            if (Schema::hasIndex('attendance_logs', ['employee_id', 'log_date'])) {
                $table->dropIndex(['employee_id', 'log_date']);
            }
        });

        Schema::table('leave_credit_ledger', function (Blueprint $table) {
            if (Schema::hasIndex('leave_credit_ledger', ['employee_id', 'leave_type_id'])) {
                $table->dropIndex(['employee_id', 'leave_type_id']);
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasIndex('documents', ['reference_no'])) {
                $table->dropIndex(['reference_no']);
            }
            if (Schema::hasIndex('documents', ['employee_id'])) {
                $table->dropIndex(['employee_id']);
            }
        });

        Schema::table('document_requests', function (Blueprint $table) {
            if (Schema::hasIndex('document_requests', ['employee_id', 'status'])) {
                $table->dropIndex(['employee_id', 'status']);
            }
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            if (Schema::hasIndex('payroll_items', ['payroll_period_id'])) {
                $table->dropIndex(['payroll_period_id']);
            }
            if (Schema::hasIndex('payroll_items', ['employee_id'])) {
                $table->dropIndex(['employee_id']);
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasIndex('audit_logs', ['model_type', 'model_id'])) {
                $table->dropIndex(['model_type', 'model_id']);
            }
            if (Schema::hasIndex('audit_logs', ['user_id'])) {
                $table->dropIndex(['user_id']);
            }
            if (Schema::hasIndex('audit_logs', ['action'])) {
                $table->dropIndex(['action']);
            }
        });
    }
};
