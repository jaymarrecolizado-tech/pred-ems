<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveType;
use App\Models\User;
use Tests\TestCase;

class LeaveModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Smoke-test against the real (seeded) MySQL database, not :memory:.
        config(['database.default' => 'mysql']);
        config([
            'database.connections.mysql.database' => 'hris',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);
    }

    private function employeeUser(): User
    {
        // Pick the lowest-id employee-role account that actually has a positive
        // VL balance, so the filing/approval tests assert against a genuinely
        // credit-entitled employee regardless of seed data or row order.
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))
            ->whereHas('leaveCredits', function ($q) {
                $q->whereHas('leaveType', fn ($t) => $t->where('code', 'VL'))
                    ->selectRaw('1')
                    ->havingRaw('COALESCE(SUM(credit - debit), 0) > 0')
                    ->groupBy('employee_id');
            })
            ->orderBy('id')
            ->firstOrFail();

        return $employee->user;
    }

    private function hrUser(): User
    {
        return User::where('email', 'hr@dictro2.gov.ph')->firstOrFail();
    }

    private function adminUser(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    public function test_employee_can_view_own_leave_page(): void
    {
        $this->actingAs($this->employeeUser())->get('/leave')->assertOk();
        $this->actingAs($this->employeeUser())->get('/leave/create')->assertOk();
    }

    public function test_employee_can_file_leave_application(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $vl = LeaveType::where('code', 'VL')->firstOrFail();

        try {
            $this->actingAs($user)->post('/leave', [
                'leave_type_id' => $vl->id,
                'date_from' => '2026-08-03',
                'date_to' => '2026-08-07',
                'reason' => 'Family vacation',
            ])->assertRedirect('/leave');

            $application = LeaveApplication::where('employee_id', $employee->id)
                ->where('leave_type_id', $vl->id)
                ->latest()
                ->firstOrFail();

            // Mon–Fri week: 5 working days.
            $this->assertEquals(5.0, (float) $application->days_applied);
            $this->assertEquals('pending', $application->status);

            $this->assertDatabaseHas('audit_logs', [
                'model_type' => LeaveApplication::class,
                'model_id' => $application->id,
                'action' => 'created',
            ]);
        } finally {
            LeaveApplication::where('employee_id', $employee->id)->where('leave_type_id', $vl->id)->delete();
        }
    }

    public function test_filing_vl_with_insufficient_balance_is_rejected(): void
    {
        // Pick an employee with no leave entitlement (balance 0 for VL).
        $employee = Employee::query()
            ->whereHas('employmentType', fn ($q) => $q->where('has_leave_credits', false))
            ->whereHas('user')
            ->with('user')
            ->firstOrFail();
        $vl = LeaveType::where('code', 'VL')->firstOrFail();

        $this->actingAs($employee->user)->post('/leave', [
            'leave_type_id' => $vl->id,
            'date_from' => '2026-08-03',
            'date_to' => '2026-08-07',
            'reason' => 'Vacation',
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('leave_applications', [
            'employee_id' => $employee->id,
            'leave_type_id' => $vl->id,
        ]);
    }

    public function test_hr_approval_debits_ledger_and_updates_status(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $vl = LeaveType::where('code', 'VL')->firstOrFail();
        $balanceBefore = (float) LeaveCreditLedger::where('employee_id', $employee->id)
            ->where('leave_type_id', $vl->id)
            ->selectRaw('COALESCE(SUM(credit - debit), 0) as b')->value('b');

        try {
            $application = LeaveApplication::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $vl->id,
                'date_from' => '2026-08-03',
                'date_to' => '2026-08-07',
                'days_applied' => 5.0,
                'reason' => 'Vacation',
                'status' => 'pending',
            ]);

            $this->actingAs($this->hrUser())
                ->post("/leave/{$application->id}/approve")
                ->assertRedirect();

            $application->refresh();
            $this->assertEquals('approved', $application->status);
            $this->assertEquals($this->hrUser()->id, $application->approver_id);

            $this->assertDatabaseHas('leave_credit_ledger', [
                'employee_id' => $employee->id,
                'leave_type_id' => $vl->id,
                'movement' => 'used',
                'source_id' => $application->id,
                'source_type' => LeaveApplication::class,
                'debit' => 5.0,
            ]);

            $balanceAfter = (float) LeaveCreditLedger::where('employee_id', $employee->id)
                ->where('leave_type_id', $vl->id)
                ->selectRaw('COALESCE(SUM(credit - debit), 0) as b')->value('b');
            $this->assertEqualsWithDelta($balanceBefore - 5.0, $balanceAfter, 0.001);
        } finally {
            LeaveCreditLedger::where('source_type', LeaveApplication::class)
                ->where('source_id', $application->id ?? 0)
                ->delete();
            if (isset($application)) {
                $application->forceDelete();
            }
        }
    }

    public function test_hr_rejection_records_reason(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $vl = LeaveType::where('code', 'VL')->firstOrFail();

        try {
            $application = LeaveApplication::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $vl->id,
                'date_from' => '2026-08-10',
                'date_to' => '2026-08-11',
                'days_applied' => 2.0,
                'reason' => 'Vacation',
                'status' => 'pending',
            ]);

            $this->actingAs($this->hrUser())->post("/leave/{$application->id}/reject", [
                'denial_reason' => 'Excess workload this week',
            ])->assertRedirect();

            $application->refresh();
            $this->assertEquals('rejected', $application->status);
            $this->assertEquals('Excess workload this week', $application->denial_reason);
        } finally {
            if (isset($application)) {
                $application->forceDelete();
            }
        }
    }

    public function test_employee_cannot_approve_or_view_approvals(): void
    {
        $this->actingAs($this->employeeUser())->get('/leave/approvals')->assertForbidden();

        $user = $this->employeeUser();
        $employee = $user->employee;
        $vl = LeaveType::where('code', 'VL')->firstOrFail();

        try {
            $application = LeaveApplication::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $vl->id,
                'date_from' => '2026-08-10',
                'date_to' => '2026-08-11',
                'days_applied' => 2.0,
                'reason' => 'Vacation',
                'status' => 'pending',
            ]);

            $this->actingAs($user)->post("/leave/{$application->id}/approve")->assertForbidden();
        } finally {
            if (isset($application)) {
                $application->forceDelete();
            }
        }
    }

    public function test_hr_and_admin_can_view_approvals(): void
    {
        $this->actingAs($this->hrUser())->get('/leave/approvals')->assertOk();
        $this->actingAs($this->adminUser())->get('/leave/approvals')->assertOk();
    }

    public function test_employee_can_cancel_own_pending_application(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $vl = LeaveType::where('code', 'VL')->firstOrFail();

        try {
            $application = LeaveApplication::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $vl->id,
                'date_from' => '2026-08-10',
                'date_to' => '2026-08-11',
                'days_applied' => 2.0,
                'reason' => 'Vacation',
                'status' => 'pending',
            ]);

            $this->actingAs($user)->post("/leave/{$application->id}/cancel")->assertRedirect('/leave');

            $application->refresh();
            $this->assertEquals('cancelled', $application->status);
        } finally {
            if (isset($application)) {
                $application->forceDelete();
            }
        }
    }

    public function test_employee_cannot_cancel_another_employees_application(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $other = Employee::where('id', '!=', $employee->id)->whereNotNull('user_id')->firstOrFail();
        $vl = LeaveType::where('code', 'VL')->firstOrFail();

        try {
            $application = LeaveApplication::create([
                'employee_id' => $other->id,
                'leave_type_id' => $vl->id,
                'date_from' => '2026-08-10',
                'date_to' => '2026-08-11',
                'days_applied' => 2.0,
                'reason' => 'Vacation',
                'status' => 'pending',
            ]);

            $this->actingAs($user)->post("/leave/{$application->id}/cancel")->assertForbidden();
            $application->refresh();
            $this->assertEquals('pending', $application->status);
        } finally {
            if (isset($application)) {
                $application->forceDelete();
            }
        }
    }

    public function test_reject_requires_a_reason(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $vl = LeaveType::where('code', 'VL')->firstOrFail();

        try {
            $application = LeaveApplication::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $vl->id,
                'date_from' => '2026-08-10',
                'date_to' => '2026-08-11',
                'days_applied' => 2.0,
                'reason' => 'Vacation',
                'status' => 'pending',
            ]);

            $this->actingAs($this->hrUser())
                ->post("/leave/{$application->id}/reject", [])
                ->assertSessionHasErrors('denial_reason');

            $application->refresh();
            $this->assertEquals('pending', $application->status);
        } finally {
            if (isset($application)) {
                $application->forceDelete();
            }
        }
    }

    public function test_payroll_cannot_manage_approvals(): void
    {
        $payroll = User::where('email', 'payroll@dictro2.gov.ph')->firstOrFail();
        $this->actingAs($payroll)->get('/leave/approvals')->assertForbidden();
    }
}
