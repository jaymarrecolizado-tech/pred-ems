<?php

namespace Tests\Feature;

use App\Models\ContributionRate;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Remittance;
use App\Models\User;
use App\Support\Payroll;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    private function admin(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    /**
     * A scratch employee with a known salary, always cleaned up afterwards.
     */
    private function payrollEmployee(float $salary, string $number): Employee
    {
        return Employee::create([
            'employee_number' => $number,
            'first_name' => 'Payroll',
            'last_name' => 'Test',
            'status' => 'active',
            'monthly_salary' => $salary,
            'salary_grade' => 11,
            'step' => 1,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Calculator */
    /* ------------------------------------------------------------------ */

    public function test_calculator_gsis_philhealth_pagibig_bir(): void
    {
        $employee = $this->payrollEmployee(32000.00, 'RO2-TEST-'.substr((string) time(), -5));
        $asOf = Carbon::parse('2026-08-15');

        try {
            $computed = Payroll::compute($employee, $asOf);

            // GSIS 9% of basic salary
            $this->assertEquals(2880.00, $computed['gsis_employee_share']);
            $this->assertEquals(3840.00, $computed['gsis_employer_share']);

            // PhilHealth 5% premium of 32,000 = 1,600, split 50/50
            $this->assertEquals(800.00, $computed['philhealth_employee_share']);
            $this->assertEquals(800.00, $computed['philhealth_employer_share']);

            // PAG-IBIG 2% of capped base 10,000 = 200 (capped at 200)
            $this->assertEquals(200.00, $computed['pagibig_employee_share']);
            $this->assertEquals(200.00, $computed['pagibig_employer_share']);

            // BIR: 32,000 × 12 = 384,000 → 15% bracket over 250,000 → 20,100/yr → 1,675/mo
            $this->assertEquals(1675.00, $computed['withholding_tax']);

            $this->assertEquals(32000.00, $computed['gross_amount']);
            $this->assertEquals(5555.00, $computed['total_deductions']);
            $this->assertEquals(26445.00, $computed['net_amount']);

            $this->assertNotEmpty($computed['trace']['lines']);
            $this->assertArrayHasKey('summary', $computed['trace']);
            $this->assertEquals(32000.00, $computed['trace']['summary']['gross']);
        } finally {
            $employee->delete();
        }
    }

    public function test_calculator_philhealth_floor_and_cap(): void
    {
        $low = $this->payrollEmployee(5000.00, 'RO2-LOW-'.substr((string) time(), -5));
        $high = $this->payrollEmployee(200000.00, 'RO2-HI-'.substr((string) time(), -5));
        $asOf = Carbon::parse('2026-08-15');

        try {
            // Below floor → minimum premium of 500, employee half = 250
            $this->assertEquals(250.00, Payroll::compute($low, $asOf)['philhealth_employee_share']);
            // Above ceiling → maximum premium of 5,000, employee half = 2,500
            $this->assertEquals(2500.00, Payroll::compute($high, $asOf)['philhealth_employee_share']);
        } finally {
            $low->delete();
            $high->delete();
        }
    }

    public function test_calculator_pagibig_low_bracket_and_cap(): void
    {
        $low = $this->payrollEmployee(1000.00, 'RO2-PI1-'.substr((string) time(), -5));
        $asOf = Carbon::parse('2026-08-15');

        try {
            $computed = Payroll::compute($low, $asOf);
            // 1,000 ≤ 1,500 → employee 1% = 10.00
            $this->assertEquals(10.00, $computed['pagibig_employee_share']);
            // employer 2% = 20.00
            $this->assertEquals(20.00, $computed['pagibig_employer_share']);
        } finally {
            $low->delete();
        }
    }

    public function test_calculator_bir_zero_tax_below_exemption(): void
    {
        $employee = $this->payrollEmployee(20000.00, 'RO2-BIR-'.substr((string) time(), -5));
        $asOf = Carbon::parse('2026-08-15');

        try {
            // 20,000 × 12 = 240,000 ≤ 250,000 exemption → no withholding
            $this->assertEquals(0.00, Payroll::compute($employee, $asOf)['withholding_tax']);
        } finally {
            $employee->delete();
        }
    }

    public function test_rates_are_effective_dated(): void
    {
        $asOf = Carbon::parse('2026-08-15');
        foreach (['GSIS', 'PHILHEALTH', 'PAGIBIG', 'BIR'] as $agency) {
            $this->assertNotNull(ContributionRate::effectiveOn($agency, $asOf), "$agency should have an active rate for 2026");
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Period lifecycle */
    /* ------------------------------------------------------------------ */

    public function test_period_flow_generate_finalize_payslips_remittances(): void
    {
        $admin = $this->admin();
        $suffix = substr((string) time(), -5);
        $periodName = "Payroll Test {$suffix}";

        try {
            // Create period (draft)
            $this->actingAs($admin)->post('/payroll', [
                'period_from' => '2026-08-01',
                'period_to' => '2026-08-31',
                'payroll_date' => '2026-08-31',
                'remarks' => $periodName,
            ])->assertRedirect();

            $period = PayrollPeriod::where('remarks', $periodName)->firstOrFail();
            $this->assertEquals(PayrollPeriod::STATUS_DRAFT, $period->status);

            // Generate items
            $this->actingAs($admin)->post("/payroll/{$period->id}/generate")->assertRedirect();
            $period->refresh();

            $this->assertGreaterThan(0, $period->items()->count());
            $item = $period->items()->first();
            $this->assertNotNull($item->computation_json);
            $this->assertGreaterThan(0, (float) $item->net_amount);

            // Finalize — locks period, issues payslips + 4 remittance summaries
            $this->actingAs($admin)->post("/payroll/{$period->id}/finalize")->assertRedirect();

            $period->refresh();
            $this->assertEquals(PayrollPeriod::STATUS_FINALIZED, $period->status);
            $this->assertNotNull($period->finalized_at);

            $this->assertSame($period->items()->count(), Payslip::whereIn('payroll_item_id', $period->items()->pluck('id'))->count());

            $from = $period->period_from->toDateString();
            $to = $period->period_to->toDateString();
            $this->assertSame(4, Remittance::where('period_from', $from)->where('period_to', $to)->count());

            $gsis = Remittance::where('agency', 'GSIS')->where('period_from', $from)->where('period_to', $to)->firstOrFail();
            $this->assertTrue($gsis->isPending());
            $this->assertGreaterThan(0, (float) $gsis->grand_total);

            // Draft period is locked — recompute must now be refused
            $this->actingAs($admin)->post("/payroll/{$period->id}/generate")->assertStatus(409);

            // Mark a remittance as remitted
            $this->actingAs($admin)->post("/payroll/remittances/{$gsis->id}/remit", [
                'reference_no' => 'BATCH-TEST-001',
            ])->assertRedirect();

            $this->assertEquals(Remittance::STATUS_REMITTED, $gsis->fresh()->status);
        } finally {
            if (isset($period)) {
                Remittance::where('period_from', $period->period_from)->where('period_to', $period->period_to)->delete();
                $period->delete(); // cascades items + payslips
            }
        }
    }

    public function test_payslip_pdf_returns_pdf(): void
    {
        $admin = $this->admin();
        $suffix = substr((string) time(), -5);

        try {
            $this->actingAs($admin)->post('/payroll', [
                'period_from' => '2026-07-01',
                'period_to' => '2026-07-31',
                'payroll_date' => '2026-07-31',
                'remarks' => "Payslip Test {$suffix}",
            ])->assertRedirect();

            $period = PayrollPeriod::where('remarks', "Payslip Test {$suffix}")->firstOrFail();
            $this->actingAs($admin)->post("/payroll/{$period->id}/generate")->assertRedirect();
            $this->actingAs($admin)->post("/payroll/{$period->id}/finalize")->assertRedirect();

            $payslip = Payslip::whereIn('payroll_item_id', $period->items()->pluck('id'))->firstOrFail();

            $response = $this->actingAs($admin)->get("/payroll/payslips/{$payslip->id}");
            $response->assertOk();
            $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
            $this->assertStringContainsString('%PDF', $response->getContent());
        } finally {
            if (isset($period)) {
                Remittance::where('period_from', $period->period_from)->where('period_to', $period->period_to)->delete();
                $period->delete();
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  RBAC */
    /* ------------------------------------------------------------------ */

    public function test_payroll_pages_require_admin_hr_or_payroll(): void
    {
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))->firstOrFail();

        $this->actingAs($employee->user)->get('/payroll')->assertForbidden();

        $this->actingAs($this->admin())->get('/payroll')->assertOk();

        $payroll = User::where('email', 'payroll@dictro2.gov.ph')->firstOrFail();
        $this->actingAs($payroll)->get('/payroll')->assertOk();
    }

    public function test_employee_self_service_payslips(): void
    {
        $admin = $this->admin();
        $suffix = substr((string) time(), -5);

        try {
            $this->actingAs($admin)->post('/payroll', [
                'period_from' => '2026-06-01',
                'period_to' => '2026-06-30',
                'payroll_date' => '2026-06-30',
                'remarks' => "Self-service Test {$suffix}",
            ])->assertRedirect();

            $period = PayrollPeriod::where('remarks', "Self-service Test {$suffix}")->firstOrFail();
            $this->actingAs($admin)->post("/payroll/{$period->id}/generate")->assertRedirect();
            $this->actingAs($admin)->post("/payroll/{$period->id}/finalize")->assertRedirect();

            $owner = Payslip::query()->with('item.employee.user')->firstOrFail();
            $employeeUser = $owner->item->employee->user;
            $this->assertNotNull($employeeUser, 'Test needs a payslip whose employee has a user account.');

            // Own payslip: list + PDF both reachable.
            $this->actingAs($employeeUser)->get('/my/payslips')->assertOk();
            $this->actingAs($employeeUser)->get("/payroll/payslips/{$owner->id}")->assertOk();

            // Another employee's payslip must be forbidden.
            $other = Payslip::query()
                ->with('item.employee.user')
                ->whereHas('item.employee', fn ($q) => $q->where('user_id', '!=', $employeeUser->id))
                ->first();
            if ($other) {
                $this->actingAs($employeeUser)->get("/payroll/payslips/{$other->id}")->assertForbidden();
            }
        } finally {
            if (isset($period)) {
                Remittance::where('period_from', $period->period_from)->where('period_to', $period->period_to)->delete();
                $period->delete();
            }
        }
    }
}
