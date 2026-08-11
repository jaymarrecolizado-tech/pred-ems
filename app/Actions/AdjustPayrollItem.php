<?php

namespace App\Actions;

use App\Models\PayrollItem;
use App\Support\Audit;
use App\Support\Payroll;

/**
 * Save per-item payroll adjustments (honoraria / overtime / other income +
 * LWOP / other deductions) and recompute the item through the full engine.
 * The computation trace — including the manual lines — is re-persisted so the
 * payslip stays auditable.
 */
class AdjustPayrollItem
{
    public function handle(PayrollItem $item, array $validated): ActionResult
    {
        $old = $item->only(['honoraria', 'overtime_pay', 'other_income', 'lwop_deduction', 'other_deductions']);

        $computed = Payroll::compute($item->employee, $item->period->period_from, [
            'honoraria' => (float) ($validated['honoraria'] ?? 0),
            'overtime_pay' => (float) ($validated['overtime_pay'] ?? 0),
            'other_income' => (float) ($validated['other_income'] ?? 0),
            'lwop' => (float) ($validated['lwop'] ?? 0),
            'other_deductions' => (float) ($validated['other_deductions'] ?? 0),
        ]);

        $item->update([
            'honoraria' => $computed['honoraria'],
            'overtime_pay' => $computed['overtime_pay'],
            'other_income' => $computed['other_income'],
            'lwop_deduction' => $computed['lwop_deduction'],
            'other_deductions' => $computed['other_deductions'],
            'gross_amount' => $computed['gross_amount'],
            'gsis_employee_share' => $computed['gsis_employee_share'],
            'philhealth_employee_share' => $computed['philhealth_employee_share'],
            'pagibig_employee_share' => $computed['pagibig_employee_share'],
            'withholding_tax' => $computed['withholding_tax'],
            'total_deductions' => $computed['total_deductions'],
            'net_amount' => $computed['net_amount'],
            'computation_json' => $computed['trace'],
        ]);

        Audit::record('adjusted', $item, $old, $item->only([
            'honoraria', 'overtime_pay', 'other_income',
            'lwop_deduction', 'other_deductions', 'gross_amount',
            'total_deductions', 'net_amount',
        ]));

        return ActionResult::ok('Adjustments saved for '.$item->employee->full_name.' — item recomputed.');
    }
}
