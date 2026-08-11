<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Support\Payroll;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the Payroll computation engine.
 *
 * These test the pure computation logic in isolation (no HTTP, no DB
 * transactions) against known statutory values. Every test uses a scratch
 * employee that is cleaned up in tearDown.
 *
 * Reference values (2025/2026 defaults):
 *   GSIS       9% EE / 12% ER on basic salary
 *   PhilHealth 5% premium (floor ₱500, ceiling ₱5,000), split 50/50
 *   PAG-IBIG   2% EE / 2% ER on monthly comp (cap base ₱10k, EE cap ₱200)
 *   BIR        TRAIN brackets annualized; PERA (₱2,000/mo) is tax-exempt
 */
class PayrollComputationTest extends TestCase
{
    private function makeEmployee(float $salary, string $number = 'RO2-UNIT-TEST'): Employee
    {
        return Employee::create([
            'employee_number' => $number.'-'.substr((string) time(), -6).'-'.random_int(1, 999),
            'first_name' => 'Unit',
            'last_name' => 'Test',
            'status' => 'active',
            'monthly_salary' => $salary,
            'salary_grade' => 11,
            'step' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up any scratch employees created by tests.
        Employee::where('first_name', 'Unit')->where('last_name', 'Test')->forceDelete();
        parent::tearDown();
    }

    /* ------------------------------------------------------------------ */
    /*  GSIS */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function gsis_is_9_percent_employee_12_percent_employer(): void
    {
        $emp = $this->makeEmployee(50000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        $this->assertEquals(4500.00, $result['gsis_employee_share']);
        $this->assertEquals(6000.00, $result['gsis_employer_share']);
    }

    #[Test]
    public function gsis_is_on_basic_salary_only_not_gross(): void
    {
        $emp = $this->makeEmployee(30000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'), [
            'honoraria' => 5000.00,
        ]);

        // GSIS should be 9% of 30,000 = 2,700, NOT 9% of 35,000
        $this->assertEquals(2700.00, $result['gsis_employee_share']);
    }

    /* ------------------------------------------------------------------ */
    /*  PhilHealth */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function philhealth_floor_applies_below_minimum(): void
    {
        $emp = $this->makeEmployee(3000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        // 3,000 × 5% = 150 → below floor of 500 → premium = 500, EE = 250
        $this->assertEquals(250.00, $result['philhealth_employee_share']);
    }

    #[Test]
    public function philhealth_cap_applies_above_ceiling(): void
    {
        $emp = $this->makeEmployee(200000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        // 200,000 × 5% = 10,000 → above ceiling of 5,000 → premium = 5,000, EE = 2,500
        $this->assertEquals(2500.00, $result['philhealth_employee_share']);
    }

    /* ------------------------------------------------------------------ */
    /*  PAG-IBIG */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function pagibig_employee_share_capped_at_200(): void
    {
        $emp = $this->makeEmployee(100000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        // 2% of capped base 10,000 = 200, which is also the cap
        $this->assertEquals(200.00, $result['pagibig_employee_share']);
    }

    #[Test]
    public function pagibig_low_bracket_for_low_salary(): void
    {
        $emp = $this->makeEmployee(1500.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        // At exactly 1,500 (low bracket threshold): 1% EE = 15
        $this->assertEquals(15.00, $result['pagibig_employee_share']);
    }

    /* ------------------------------------------------------------------ */
    /*  BIR Withholding */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function bir_zero_tax_for_low_income(): void
    {
        $emp = $this->makeEmployee(20000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        // 20,000 × 12 = 240,000 ≤ 250,000 → exempt
        $this->assertEquals(0.00, $result['withholding_tax']);
    }

    #[Test]
    public function bir_15_percent_bracket(): void
    {
        // 30,000 × 12 = 360,000 → bracket 250k-400k → (360k-250k) × 15% = 16,500/yr → 1,375/mo
        $emp = $this->makeEmployee(30000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        $this->assertEquals(1375.00, $result['withholding_tax']);
    }

    #[Test]
    public function bir_20_percent_bracket(): void
    {
        // 40,000 × 12 = 480,000 → bracket 400k-800k → 22,500 + (480k-400k) × 20% = 38,500/yr → 3,208.33/mo
        $emp = $this->makeEmployee(40000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        $this->assertEquals(round(38500 / 12, 2), $result['withholding_tax']);
    }

    #[Test]
    public function bir_35_percent_top_bracket(): void
    {
        // 1,000,000 × 12 = 12,000,000 → bracket 8M+ → 2,202,500 + (12M-8M) × 35% = 3,602,500/yr → 300,208.33/mo
        $emp = $this->makeEmployee(1000000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        $this->assertEquals(round(3602500 / 12, 2), $result['withholding_tax']);
    }

    /* ------------------------------------------------------------------ */
    /*  Extras: honoraria, overtime, other income, LWOP */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function honoraria_added_to_gross_and_taxable_income(): void
    {
        $emp = $this->makeEmployee(25000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'), [
            'honoraria' => 5000.00,
        ]);

        $this->assertEquals(5000.00, $result['honoraria']);
        $this->assertEquals(30000.00, $result['gross_amount']);
    }

    #[Test]
    public function lwop_deducted_from_net(): void
    {
        $emp = $this->makeEmployee(50000.00);
        $withoutLwop = Payroll::compute($emp, Carbon::parse('2026-08-15'));
        $withLwop = Payroll::compute($emp, Carbon::parse('2026-08-15'), [
            'lwop' => 3000.00,
        ]);

        $this->assertEquals(3000.00, $withLwop['lwop_deduction']);
        $this->assertEquals(
            $withoutLwop['net_amount'] - 3000.00,
            $withLwop['net_amount']
        );
    }

    #[Test]
    public function other_deductions_added_to_total(): void
    {
        $emp = $this->makeEmployee(40000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'), [
            'other_deductions' => 1500.00,
        ]);

        $this->assertEquals(1500.00, $result['other_deductions']);
    }

    /* ------------------------------------------------------------------ */
    /*  Negative guards */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function negative_salary_treated_as_zero(): void
    {
        $emp = $this->makeEmployee(-1000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        $this->assertEquals(0.00, $result['basic_salary']);
        $this->assertEquals(0.00, $result['gsis_employee_share']);
        $this->assertEquals(0.00, $result['withholding_tax']);
    }

    #[Test]
    public function negative_extras_treated_as_zero(): void
    {
        $emp = $this->makeEmployee(30000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'), [
            'honoraria' => -5000.00,
            'lwop' => -3000.00,
        ]);

        $this->assertEquals(0.00, $result['honoraria']);
        $this->assertEquals(0.00, $result['lwop_deduction']);
    }

    /* ------------------------------------------------------------------ */
    /*  Trace / audit structure */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function trace_has_lines_and_summary(): void
    {
        $emp = $this->makeEmployee(35000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        $this->assertIsArray($result['trace']);
        $this->assertArrayHasKey('lines', $result['trace']);
        $this->assertArrayHasKey('summary', $result['trace']);
        $this->assertNotEmpty($result['trace']['lines']);

        // Summary keys must match the returned top-level keys
        $summary = $result['trace']['summary'];
        $this->assertEquals($result['gross_amount'], $summary['gross']);
        $this->assertEquals($result['net_amount'], $summary['net']);
        $this->assertEquals($result['total_deductions'], $summary['total_deductions']);
    }

    #[Test]
    public function trace_contains_employee_info(): void
    {
        $emp = $this->makeEmployee(25000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        $this->assertEquals('Unit Test', $result['trace']['employee']);
        $this->assertEquals('2026-08-15', $result['trace']['as_of']);
    }

    /* ------------------------------------------------------------------ */
    /*  Net pay consistency */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function net_equals_gross_minus_all_deductions(): void
    {
        $emp = $this->makeEmployee(45000.00);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'), [
            'honoraria' => 2000.00,
            'lwop' => 1000.00,
            'other_deductions' => 500.00,
        ]);

        $expectedDeductions = $result['gsis_employee_share']
            + $result['philhealth_employee_share']
            + $result['pagibig_employee_share']
            + $result['withholding_tax']
            + $result['lwop_deduction']
            + $result['other_deductions'];

        $this->assertEquals($expectedDeductions, $result['total_deductions']);
        $this->assertEquals(
            round($result['gross_amount'] - $expectedDeductions, 2),
            $result['net_amount']
        );
    }

    #[Test]
    #[DataProvider('salaryProvider')]
    public function computation_is_consistent_across_salaries(float $salary): void
    {
        $emp = $this->makeEmployee($salary);
        $result = Payroll::compute($emp, Carbon::parse('2026-08-15'));

        // Gross >= basic (no PERA by default in unit tests, so gross == basic)
        $this->assertEqualsWithDelta($salary, $result['gross_amount'], 0.01);

        // Net > 0 for any positive salary
        $this->assertGreaterThan(0, $result['net_amount']);

        // All shares are non-negative
        $this->assertGreaterThanOrEqual(0, $result['gsis_employee_share']);
        $this->assertGreaterThanOrEqual(0, $result['philhealth_employee_share']);
        $this->assertGreaterThanOrEqual(0, $result['pagibig_employee_share']);
        $this->assertGreaterThanOrEqual(0, $result['withholding_tax']);
    }

    public static function salaryProvider(): array
    {
        return [
            'minimum' => [5000.00],
            'low' => [15000.00],
            'medium' => [35000.00],
            'high' => [80000.00],
            'very high' => [250000.00],
        ];
    }
}
