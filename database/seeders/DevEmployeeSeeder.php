<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Sample data for local development ONLY. Never run on production.
 *
 * Creates: admin/hr/payroll/unit_head/employee accounts + employees of each
 * employment type + opening leave credits + a sample appointment.
 */
class DevEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DevEmployeeSeeder skipped in production environment.');

            return;
        }

        $now = now();
        $password = Hash::make('!Password123');

        // ---- Users ----
        $users = [
            ['name' => 'Admin User',       'email' => 'admin@dictro2.gov.ph',   'role' => 'admin'],
            ['name' => 'HR Officer',       'email' => 'hr@dictro2.gov.ph',      'role' => 'hr'],
            ['name' => 'Payroll Officer',  'email' => 'payroll@dictro2.gov.ph', 'role' => 'payroll'],
            ['name' => 'Unit Head',        'email' => 'head@dictro2.gov.ph',    'role' => 'unit_head'],
            ['name' => 'Juan Dela Cruz',   'email' => 'juan@dictro2.gov.ph',    'role' => 'employee'],
            ['name' => 'Maria Santos',     'email' => 'maria@dictro2.gov.ph',   'role' => 'employee'],
            ['name' => 'Pedro Reyes',      'email' => 'pedro@dictro2.gov.ph',   'role' => 'employee'],
        ];

        $typeIds = DB::table('employment_types')->pluck('id', 'code');
        $divId = DB::table('divisions')->value('id');
        $posId = DB::table('positions')->value('id');

        $employeeSeed = [
            // [userKey, employeeNumber, first, last, gender, typeCode, salaryGrade, step, monthlySalary, dateOriginal]
            ['juan',  'RO2-0001', 'Juan',   'Dela Cruz', 'Male',   'PERMANENT',          18, 3, 67400.00, '2015-06-01'],
            ['maria', 'RO2-0002', 'Maria',  'Santos',    'Female', 'CONTRACTUAL',        15, 1, 48000.00, '2023-03-16'],
            ['pedro', 'RO2-0003', 'Pedro',  'Reyes',     'Male',   'JOB_ORDER',          10, 1, 15000.00, '2024-07-01'],
            [null,    'RO2-0004', 'Ana',    'Garcia',    'Female', 'CONTRACT_OF_SERVICE', 12, 1, 25000.00, '2024-11-04'],
            [null,    'RO2-0005', 'Luis',   'Torres',    'Male',   'GIP',                8,  1, 12000.00, '2025-06-02'],
            [null,    'RO2-0006', 'Carmen', 'Bautista',  'Female', 'CASUAL',             11, 2, 36400.00, '2019-01-02'],
        ];

        foreach ($users as $u) {
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ($employeeSeed as [$userKey, $empNo, $first, $last, $gender, $typeCode, $grade, $step, $salary, $dateOrig]) {
            $userId = $userKey ? DB::table('users')->where('email', $userKey.'@dictro2.gov.ph')->value('id') : null;

            DB::table('employees')->updateOrInsert(
                ['employee_number' => $empNo],
                [
                    'user_id' => $userId,
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'birth_date' => '1990-01-01',
                    'employment_type_id' => $typeIds[$typeCode] ?? null,
                    'division_id' => $divId,
                    'position_id' => $posId,
                    'salary_grade' => $grade,
                    'step' => $step,
                    'monthly_salary' => $salary,
                    'date_original_appointment' => $dateOrig,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $empId = DB::table('employees')->where('employee_number', $empNo)->value('id');

            // Sample appointment (service record source)
            if (! DB::table('appointments')->where('employee_id', $empId)->exists()) {
                DB::table('appointments')->insert([
                    'employee_id' => $empId,
                    'position_id' => $posId,
                    'division_id' => $divId,
                    'employment_type_id' => $typeIds[$typeCode] ?? null,
                    'appointment_type' => 'original',
                    'appointment_status' => 'approved',
                    'salary_grade' => $grade,
                    'step' => $step,
                    'monthly_salary' => $salary,
                    'effective_from' => $dateOrig,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Opening leave credits for employees with leave entitlement
            $type = DB::table('employment_types')->where('id', $typeIds[$typeCode] ?? 0)->first();
            if ($type && $type->has_leave_credits) {
                foreach (['VL' => 30, 'SL' => 15] as $code => $days) {
                    $leaveTypeId = DB::table('leave_types')->where('code', $code)->value('id');
                    if ($leaveTypeId && ! DB::table('leave_credit_ledger')->where('employee_id', $empId)->where('leave_type_id', $leaveTypeId)->exists()) {
                        DB::table('leave_credit_ledger')->insert([
                            'employee_id' => $empId,
                            'leave_type_id' => $leaveTypeId,
                            'transaction_date' => $dateOrig,
                            'movement' => 'opening_balance',
                            'credit' => $days,
                            'debit' => 0,
                            'balance_after' => $days,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }

        // Assign roles to users
        foreach ($users as $u) {
            $userId = DB::table('users')->where('email', $u['email'])->value('id');
            $roleId = DB::table('roles')->where('name', $u['role'])->value('id');
            if ($userId && $roleId) {
                DB::table('role_user')->updateOrInsert(['role_id' => $roleId, 'user_id' => $userId], []);
            }
        }
    }
}
