<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\LeaveApplication;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveMonetization;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveMonetizedNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CSC leave compliance (Omnibus Rules on Leave, MC 41 s. 1998 as amended):
 * SLP annual grants, VL monetization rules, forced-leave monitoring and the
 * CSC Form No. 6 application PDF.
 */
class LeaveComplianceTest extends TestCase
{
    private function adminUser(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    private function employeeUser(): User
    {
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->firstOrFail();

        return $employee->user;
    }

    /**
     * A dedicated leave-entitled employee with a controlled VL credit so the
     * CSC rule checks are deterministic (no user account attached).
     */
    private function makeEmployee(float $vlCredit, float $salary = 22000.0): Employee
    {
        $type = EmploymentType::where('has_leave_credits', true)->firstOrFail();
        $vl = LeaveType::where('code', 'VL')->firstOrFail();

        $employee = Employee::create([
            'employee_number' => 'TST-'.strtoupper(Str::random(8)),
            'first_name' => 'Rule',
            'last_name' => 'Test'.random_int(100, 999),
            'employment_type_id' => $type->id,
            'status' => 'active',
            'monthly_salary' => $salary,
            'date_original_appointment' => '2020-01-01',
        ]);

        if ($vlCredit > 0) {
            LeaveCreditLedger::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $vl->id,
                'transaction_date' => now()->toDateString(),
                'movement' => 'opening_balance',
                'credit' => $vlCredit,
                'debit' => 0,
                'balance_after' => $vlCredit,
                'remarks' => 'Test fixture',
            ]);
        }

        return $employee;
    }

    /* ------------------------------------------------------------------ */
    /*  SLP annual grant (3 days/year, non-cumulative) */
    /* ------------------------------------------------------------------ */

    public function test_slp_annual_grant_resets_and_grants_per_year(): void
    {
        $type = EmploymentType::where('has_leave_credits', true)->firstOrFail();
        $slp = LeaveType::where('code', 'SLP')->firstOrFail();
        $this->assertTrue((bool) $slp->annual_grant);

        $employee = Employee::create([
            'employee_number' => 'TST-'.strtoupper(Str::random(8)),
            'first_name' => 'SLP',
            'last_name' => 'Grant',
            'employment_type_id' => $type->id,
            'date_original_appointment' => '2024-06-01',
            'status' => 'active',
            'monthly_salary' => 30000,
        ]);

        try {
            Artisan::call('leave:accrue', ['--employee' => $employee->id, '--as-of' => '2026-12']);

            // 2024, 2025, 2026 grants; leftover reset before 2025 and 2026.
            $this->assertSame(3, LeaveCreditLedger::where('employee_id', $employee->id)
                ->where('leave_type_id', $slp->id)->where('movement', 'grant')->count());
            $this->assertSame(2, LeaveCreditLedger::where('employee_id', $employee->id)
                ->where('leave_type_id', $slp->id)->where('movement', 'reset')->count());

            $this->assertEqualsWithDelta(3.0, $employee->fresh()->leaveBalanceFor($slp), 0.001);

            // Idempotent — a second run adds nothing.
            Artisan::call('leave:accrue', ['--employee' => $employee->id, '--as-of' => '2026-12']);
            $this->assertSame(3, LeaveCreditLedger::where('employee_id', $employee->id)
                ->where('leave_type_id', $slp->id)->where('movement', 'grant')->count());

            // Non-cumulative: using a day then rolling into the next year resets
            // the leftover and re-grants the full 3 — never carries over.
            LeaveCreditLedger::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $slp->id,
                'transaction_date' => '2026-08-01',
                'movement' => 'used',
                'credit' => 0,
                'debit' => 1,
                'balance_after' => $employee->fresh()->leaveBalanceFor($slp) - 1,
                'remarks' => 'Test use',
            ]);

            Artisan::call('leave:accrue', ['--employee' => $employee->id, '--as-of' => '2027-01']);
            $this->assertEqualsWithDelta(3.0, $employee->fresh()->leaveBalanceFor($slp), 0.001);
        } finally {
            $employee->forceDelete(); // cascades ledger rows (employee FK)
        }
    }

    /* ------------------------------------------------------------------ */
    /*  VL monetization */
    /* ------------------------------------------------------------------ */

    public function test_hr_processes_vl_monetization_and_issues_voucher(): void
    {
        $employee = $this->employeeUser()->employee;
        $vl = LeaveType::where('code', 'VL')->firstOrFail();

        $topUp = null;
        $balanceBefore = $employee->leaveBalanceFor($vl);
        if ($balanceBefore < 10.5) {
            $topUp = LeaveCreditLedger::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $vl->id,
                'transaction_date' => now()->toDateString(),
                'movement' => 'opening_balance',
                'credit' => 20,
                'debit' => 0,
                'balance_after' => $balanceBefore + 20,
                'remarks' => 'Test top-up',
            ]);
            $balanceBefore = $employee->fresh()->leaveBalanceFor($vl);
        }

        try {
            $this->actingAs($this->adminUser())->post('/leave/monetization', [
                'employee_id' => $employee->id,
                'year' => now()->year,
                'days' => 5,
                'remarks' => 'Test monetization',
            ])->assertRedirect();

            $monetization = LeaveMonetization::where('employee_id', $employee->id)
                ->where('remarks', 'Test monetization')->latest()->first();
            $this->assertNotNull($monetization);
            $this->assertStringStartsWith('MO-'.now()->year.'-', $monetization->reference_no);
            $this->assertEquals(5.0, (float) $monetization->days);
            $this->assertEqualsWithDelta(round($employee->monthly_salary / 22, 2), (float) $monetization->per_day_rate, 0.01);
            $this->assertEqualsWithDelta(round($monetization->per_day_rate * 5, 2), (float) $monetization->gross_amount, 0.01);

            // Ledger debited with the monetized movement.
            $this->assertDatabaseHas('leave_credit_ledger', [
                'employee_id' => $employee->id,
                'leave_type_id' => $vl->id,
                'movement' => 'monetized',
                'source_type' => LeaveMonetization::class,
                'source_id' => $monetization->id,
                'debit' => 5.0,
            ]);
            $this->assertEqualsWithDelta($balanceBefore - 5.0, $employee->fresh()->leaveBalanceFor($vl), 0.001);

            // Employee notified.
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $employee->user_id,
                'type' => LeaveMonetizedNotification::class,
            ]);

            // Voucher PDF downloads.
            $voucher = $this->actingAs($this->adminUser())
                ->get("/leave/monetizations/{$monetization->id}/voucher");
            $voucher->assertOk();
            $this->assertStringContainsString('application/pdf', $voucher->headers->get('Content-Type') ?: '');
        } finally {
            foreach (LeaveMonetization::where('employee_id', $employee->id)->where('remarks', 'Test monetization')->get() as $row) {
                LeaveCreditLedger::where('source_type', LeaveMonetization::class)->where('source_id', $row->id)->delete();
            }
            LeaveMonetization::where('employee_id', $employee->id)->where('remarks', 'Test monetization')->delete();
            DB::table('notifications')->where('notifiable_id', $employee->user_id)
                ->where('type', LeaveMonetizedNotification::class)->delete();
            $topUp?->delete();
        }
    }

    public function test_monetization_enforces_csc_rules(): void
    {
        $admin = $this->adminUser();
        $vl = LeaveType::where('code', 'VL')->firstOrFail();
        $created = [];

        try {
            // Below the 10-day accumulation threshold.
            $low = $this->makeEmployee(9.0);
            $created[] = $low;
            $this->actingAs($admin)->post('/leave/monetization', [
                'employee_id' => $low->id, 'year' => now()->year, 'days' => 2,
            ])->assertSessionHas('error');

            // Retain-at-least-5 rule: 12 − 8 leaves only 4.
            $retain = $this->makeEmployee(12.0);
            $created[] = $retain;
            $this->actingAs($admin)->post('/leave/monetization', [
                'employee_id' => $retain->id, 'year' => now()->year, 'days' => 8,
            ])->assertSessionHas('error');
            $this->assertSame(0, LeaveMonetization::where('employee_id', $retain->id)->count());

            // Legit 7-day monetization (12 − 7 retains exactly 5), then the
            // 30-days-per-year cap blocks the second batch.
            $this->actingAs($admin)->post('/leave/monetization', [
                'employee_id' => $retain->id, 'year' => now()->year, 'days' => 7, 'remarks' => 'Test rule',
            ])->assertRedirect();
            $this->assertSame(1, LeaveMonetization::where('employee_id', $retain->id)->count());

            $this->actingAs($admin)->post('/leave/monetization', [
                'employee_id' => $retain->id, 'year' => now()->year, 'days' => 7,
            ])->assertSessionHas('error');
            $this->assertSame(1, LeaveMonetization::where('employee_id', $retain->id)->count());
        } finally {
            foreach ($created as $employee) {
                LeaveMonetization::where('employee_id', $employee->id)->delete();
                LeaveCreditLedger::where('employee_id', $employee->id)
                    ->where('source_type', LeaveMonetization::class)->delete();
                $employee->forceDelete();
            }
        }
    }

    public function test_employee_cannot_access_monetization_admin(): void
    {
        $this->actingAs($this->employeeUser())->get('/leave/monetization')->assertForbidden();
    }

    public function test_employee_sees_own_monetization_history_on_leave_page(): void
    {
        $this->actingAs($this->employeeUser())
            ->get('/leave')
            ->assertOk()
            ->assertSee('My VL Monetizations');
    }

    /* ------------------------------------------------------------------ */
    /*  Forced leave monitoring report (CSC) */
    /* ------------------------------------------------------------------ */

    public function test_forced_leave_report_renders_and_exports_all_formats(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/reports/forced-leave')
            ->assertOk()
            ->assertSee('Forced Leave Compliance')
            ->assertSee('Non-compliant');

        $csv = $this->actingAs($this->adminUser())->get('/reports/forced-leave?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type') ?: '');
        $this->assertStringContainsString('VL Balance', $csv->getContent());

        $this->actingAs($this->adminUser())->get('/reports/forced-leave?format=pdf')->assertOk();
        $this->actingAs($this->adminUser())->get('/reports/forced-leave?format=xls')->assertOk();
    }

    public function test_employees_cannot_access_forced_leave_report(): void
    {
        $this->actingAs($this->employeeUser())->get('/reports/forced-leave')->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /*  CSC Form No. 6 (Application for Leave) */
    /* ------------------------------------------------------------------ */

    public function test_employee_can_download_own_csc_form6(): void
    {
        $user = $this->employeeUser();
        $vl = LeaveType::where('code', 'VL')->firstOrFail();

        try {
            $application = LeaveApplication::create([
                'employee_id' => $user->employee->id,
                'leave_type_id' => $vl->id,
                'date_from' => '2026-08-10',
                'date_to' => '2026-08-11',
                'days_applied' => 2.0,
                'reason' => 'Family matter',
                'status' => 'pending',
            ]);

            $response = $this->actingAs($user)->get("/leave/{$application->id}/form6");
            $response->assertOk();
            $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?: '');
        } finally {
            if (isset($application)) {
                $application->forceDelete();
            }
        }
    }

    public function test_employee_cannot_download_another_employees_form6(): void
    {
        $user = $this->employeeUser();
        $other = Employee::where('id', '!=', $user->employee->id)
            ->whereNotNull('user_id')->firstOrFail();
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

            $this->actingAs($user)->get("/leave/{$application->id}/form6")->assertForbidden();
        } finally {
            if (isset($application)) {
                $application->forceDelete();
            }
        }
    }
}
