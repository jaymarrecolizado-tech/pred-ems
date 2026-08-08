<?php

namespace App\Support;

use App\Models\ContributionRate;
use App\Models\Employee;
use Carbon\CarbonInterface;

/**
 * Statutory payroll computation engine (GSIS, PhilHealth, PAG-IBIG, BIR).
 *
 * All rates come from `contribution_rates` config rows (effective-dated), so
 * annual rate changes are data edits — never code changes. Every computation
 * returns a full `trace` so the resulting payroll item is auditable years
 * later (the controller persists it in payroll_items.computation_json).
 *
 * 2025/2026 reference values (from ContributionRateSeeder):
 *   GSIS       9% employee / 12% employer on basic salary
 *   PhilHealth 5% premium on basic salary (floor ₱500, ceiling ₱5,000), split 50/50
 *   PAG-IBIG   2% EE / 2% ER on monthly compensation (₱1,500 low bracket 1%/2%),
 *              employee share capped at ₱200
 *   BIR        TRAIN brackets annualized; PERA (₱2,000/mo) is tax-exempt
 */
class Payroll
{
    /**
     * Compute the full payroll picture for an employee as of a date.
     *
     * @param  array{honoraria?: float, overtime_pay?: float, other_income?: float, lwop?: float, other_deductions?: float}  $extras
     * @return array{
     *     basic_salary: float, pera: float, honoraria: float, overtime_pay: float,
     *     other_income: float, income_lines: array, gross_amount: float,
     *     gsis_employee_share: float, gsis_employer_share: float,
     *     philhealth_employee_share: float, philhealth_employer_share: float,
     *     pagibig_employee_share: float, pagibig_employer_share: float,
     *     withholding_tax: float, lwop_deduction: float, other_deductions: float,
     *     deduction_lines: array, total_deductions: float, net_amount: float, trace: array
     * }
     */
    public static function compute(Employee $employee, CarbonInterface $asOf, array $extras = []): array
    {
        $trace = [
            'employee' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'as_of' => $asOf->toDateString(),
            'lines' => [],
        ];

        $traceLine = function (string $label, float $base, ?float $rate, float $amount, string $note = '') use (&$trace) {
            // Only nulls are dropped — a zero amount stays (it is valid data).
            $trace['lines'][] = array_filter([
                'label' => $label,
                'base' => $base > 0 ? round($base, 2) : null,
                'rate' => $rate !== null ? $rate . '%' : null,
                'amount' => round($amount, 2),
                'note' => $note ?: null,
            ], fn ($value) => $value !== null);
        };

        /* ---------------------------------------------------------------- */
        /*  Income                                                           */
        /* ---------------------------------------------------------------- */

        $basicSalary = max(0, (float) $employee->monthly_salary);
        $traceLine('Basic salary', $basicSalary, null, $basicSalary);

        // PERA — from the employee's active PERA allowance rows.
        $pera = 0.0;
        $peraLines = [];
        foreach ($employee->allowances as $empAllowance) {
            $allowance = $empAllowance->allowance;
            if (! $allowance?->is_active) {
                continue;
            }
            if ($empAllowance->effective_from && $empAllowance->effective_from->gt($asOf)) {
                continue;
            }
            if ($empAllowance->effective_to && $empAllowance->effective_to->lt($asOf)) {
                continue;
            }
            if (strtoupper((string) $allowance->code) === 'PERA') {
                $pera += (float) $empAllowance->amount;
                $peraLines[] = "{$allowance->name}: " . number_format((float) $empAllowance->amount, 2);
            }
        }
        if ($peraLines) {
            $traceLine('PERA (exempt from tax)', $pera, null, $pera, implode('; ', $peraLines));
        } else {
            $pera = 0.0;
            $traceLine('PERA', 0, null, 0, 'No active PERA allowance on file');
        }

        $honoraria = max(0, (float) ($extras['honoraria'] ?? 0));
        $overtimePay = max(0, (float) ($extras['overtime_pay'] ?? 0));
        $otherIncome = max(0, (float) ($extras['other_income'] ?? 0));
        foreach (['honoraria' => $honoraria, 'overtime_pay' => $overtimePay, 'other_income' => $otherIncome] as $label => $value) {
            if ($value > 0) {
                $traceLine(ucwords(str_replace('_', ' ', $label)), $value, null, $value);
            }
        }

        $gross = round($basicSalary + $pera + $honoraria + $overtimePay + $otherIncome, 2);

        /* ---------------------------------------------------------------- */
        /*  GSIS — premium on basic salary only                             */
        /* ---------------------------------------------------------------- */

        $gsis = ContributionRate::effectiveOn('GSIS', $asOf);
        $gsisCfg = $gsis?->config ?? ['employee_rate' => 9, 'employer_rate' => 12];
        $gsisEe = round($basicSalary * ((float) $gsisCfg['employee_rate'] / 100), 2);
        $gsisEr = round($basicSalary * ((float) $gsisCfg['employer_rate'] / 100), 2);
        $traceLine('GSIS (employee)', $basicSalary, (float) $gsisCfg['employee_rate'], $gsisEe, $gsis?->name ?? 'Default 9%');

        /* ---------------------------------------------------------------- */
        /*  PhilHealth — 5% premium, floor/ceiling, split 50/50             */
        /* ---------------------------------------------------------------- */

        $ph = ContributionRate::effectiveOn('PHILHEALTH', $asOf);
        $phCfg = $ph?->config ?? ['rate' => 5, 'employee_share' => 2.5, 'employer_share' => 2.5, 'min_premium' => 500, 'max_premium' => 5000];
        $phPremium = $basicSalary * ((float) $phCfg['rate'] / 100);
        $phPremium = max((float) ($phCfg['min_premium'] ?? 0), min((float) ($phCfg['max_premium'] ?? PHP_FLOAT_MAX), $phPremium));
        $phEe = round($phPremium * ((float) ($phCfg['employee_share'] ?? 2.5) / (float) $phCfg['rate']), 2);
        $phEr = round($phPremium * ((float) ($phCfg['employer_share'] ?? 2.5) / (float) $phCfg['rate']), 2);
        $traceLine('PhilHealth (employee)', $basicSalary, (float) $phCfg['employee_share'], $phEe, 'Premium ₱' . number_format($phPremium, 2) . ' (' . $ph?->name ?? 'Default 5%' . ')');

        /* ---------------------------------------------------------------- */
        /*  PAG-IBIG — % of monthly compensation, employee capped            */
        /* ---------------------------------------------------------------- */

        $pagibig = ContributionRate::effectiveOn('PAGIBIG', $asOf);
        $piCfg = $pagibig?->config ?? [
            'low_bracket' => 1500, 'low_employee' => 1, 'low_employer' => 2,
            'std_employee' => 2, 'std_employer' => 2,
            'cap_base' => 10000, 'cap_employee' => 200, 'cap_employer' => 200,
        ];
        $monthlyComp = $gross; // basic + PERA + all other income
        $lowBracket = (float) ($piCfg['low_bracket'] ?? 1500);
        $piEePct = $monthlyComp <= $lowBracket ? (float) ($piCfg['low_employee'] ?? 1) : (float) ($piCfg['std_employee'] ?? 2);
        $piErPct = $monthlyComp <= $lowBracket ? (float) ($piCfg['low_employer'] ?? 2) : (float) ($piCfg['std_employer'] ?? 2);
        $piBase = min($monthlyComp, (float) ($piCfg['cap_base'] ?? 10000));
        $piEe = round(min($piBase * ($piEePct / 100), (float) ($piCfg['cap_employee'] ?? 200)), 2);
        $piEr = round(min($piBase * ($piErPct / 100), (float) ($piCfg['cap_employer'] ?? 200)), 2);
        $traceLine('PAG-IBIG (employee)', $piBase, $piEePct, $piEe, 'Monthly compensation ₱' . number_format($monthlyComp, 2));

        /* ---------------------------------------------------------------- */
        /*  BIR withholding — TRAIN brackets, annualized                     */
        /* ---------------------------------------------------------------- */

        // PERA is exempt from income tax (BIR exemption); honoraria, overtime
        // and other taxable income join the basic salary in the tax base.
        $taxableMonthly = $basicSalary + $honoraria + $overtimePay + $otherIncome;
        $bir = ContributionRate::effectiveOn('BIR', $asOf);
        $birCfg = $bir?->config ?? [];
        $brackets = $birCfg['brackets'] ?? [
            ['min' => 0, 'max' => 250000, 'base' => 0, 'rate' => 0],
            ['min' => 250000, 'max' => 400000, 'base' => 0, 'rate' => 15],
            ['min' => 400000, 'max' => 800000, 'base' => 22500, 'rate' => 20],
            ['min' => 800000, 'max' => 2000000, 'base' => 102500, 'rate' => 25],
            ['min' => 2000000, 'max' => 8000000, 'base' => 402500, 'rate' => 30],
            ['min' => 8000000, 'max' => null, 'base' => 2202500, 'rate' => 35],
        ];
        $annualTaxable = $taxableMonthly * 12;
        $annualTax = 0.0;
        $appliedBracket = null;
        foreach ($brackets as $bracket) {
            $min = (float) $bracket['min'];
            $max = $bracket['max'] === null ? PHP_FLOAT_MAX : (float) $bracket['max'];
            if ($annualTaxable >= $min && $annualTaxable < $max) {
                $annualTax = (float) $bracket['base'] + ($annualTaxable - $min) * ((float) $bracket['rate'] / 100);
                $appliedBracket = $bracket;
                break;
            }
        }
        $monthlyWithholding = round($annualTax / 12, 2);
        $traceLine('BIR withholding tax', $taxableMonthly, $appliedBracket ? (float) $appliedBracket['rate'] : 0, $monthlyWithholding,
            'Annualized ₱' . number_format($annualTaxable, 2) . ' → annual tax ₱' . number_format($annualTax, 2) . ' (' . ($bir?->name ?? 'TRAIN') . ')');

        /* ---------------------------------------------------------------- */
        /*  Other deductions (LWOP, manual lines)                            */
        /* ---------------------------------------------------------------- */

        $lwop = max(0, (float) ($extras['lwop'] ?? 0));
        $otherDeductions = max(0, (float) ($extras['other_deductions'] ?? 0));
        if ($lwop > 0) {
            $traceLine('Leave without pay (LWOP)', $lwop, null, $lwop);
        }
        if ($otherDeductions > 0) {
            $traceLine('Other deductions', $otherDeductions, null, $otherDeductions);
        }

        /* ---------------------------------------------------------------- */
        /*  Totals                                                           */
        /* ---------------------------------------------------------------- */

        $eeShares = $gsisEe + $phEe + $piEe;
        $totalDeductions = round($eeShares + $monthlyWithholding + $lwop + $otherDeductions, 2);
        $net = round($gross - $totalDeductions, 2);

        $trace['summary'] = [
            'gross' => $gross,
            'gsis_ee' => $gsisEe, 'gsis_er' => $gsisEr,
            'philhealth_ee' => $phEe, 'philhealth_er' => $phEr,
            'pagibig_ee' => $piEe, 'pagibig_er' => $piEr,
            'withholding_tax' => $monthlyWithholding,
            'total_deductions' => $totalDeductions,
            'net' => $net,
        ];

        return [
            'basic_salary' => $basicSalary,
            'pera' => $pera,
            'honoraria' => $honoraria,
            'overtime_pay' => $overtimePay,
            'other_income' => $otherIncome,
            'income_lines' => $trace['lines'],
            'gross_amount' => $gross,

            'gsis_employee_share' => $gsisEe,
            'gsis_employer_share' => $gsisEr,
            'philhealth_employee_share' => $phEe,
            'philhealth_employer_share' => $phEr,
            'pagibig_employee_share' => $piEe,
            'pagibig_employer_share' => $piEr,
            'withholding_tax' => $monthlyWithholding,
            'lwop_deduction' => $lwop,
            'other_deductions' => $otherDeductions,
            'deduction_lines' => $trace['lines'],
            'total_deductions' => $totalDeductions,
            'net_amount' => $net,
            'trace' => $trace,
        ];
    }

    /**
     * Employees eligible for a payroll run: active status with a salary on file.
     */
    public static function eligibleEmployees(): \Illuminate\Support\Collection
    {
        return Employee::query()
            ->with(['allowances.allowance', 'position', 'employmentType'])
            ->where('status', 'active')
            ->whereNotNull('monthly_salary')
            ->where('monthly_salary', '>', 0)
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Next sequential payslip reference, e.g. PS-2026-0001.
     */
    public static function nextPayslipRef(): string
    {
        $prefix = 'PS-' . now()->format('Y') . '-';
        $last = \App\Models\Payslip::where('reference_no', 'like', $prefix . '%')
            ->orderByDesc('reference_no')
            ->value('reference_no');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
