<?php

namespace App\Actions;

use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Support\Audit;
use App\Support\Payroll;
use Illuminate\Support\Facades\DB;

/**
 * Compute (or recompute) payroll items for a draft period. Each eligible
 * employee gets one item with the full computation trace persisted for audit;
 * manual per-item adjustments (honoraria / overtime / LWOP) are preserved
 * across recomputes.
 */
class GeneratePayrollItems
{
    public function handle(PayrollPeriod $period): ActionResult
    {
        $asOf = $period->period_from;
        $count = 0;

        DB::transaction(function () use ($period, $asOf, &$count) {
            // Preserve any manual per-item adjustments already entered so a
            // recompute updates the statutory lines without wiping honoraria /
            // overtime / LWOP entries.
            $existing = PayrollItem::where('payroll_period_id', $period->id)
                ->get(['employee_id', 'honoraria', 'overtime_pay', 'other_income', 'lwop_deduction', 'other_deductions'])
                ->keyBy('employee_id');

            PayrollItem::where('payroll_period_id', $period->id)->delete();

            foreach (Payroll::eligibleEmployees() as $employee) {
                $prev = $existing->get($employee->id);

                $computed = Payroll::compute($employee, $asOf, [
                    'honoraria' => (float) ($prev?->honoraria ?? 0),
                    'overtime_pay' => (float) ($prev?->overtime_pay ?? 0),
                    'other_income' => (float) ($prev?->other_income ?? 0),
                    'lwop' => (float) ($prev?->lwop_deduction ?? 0),
                    'other_deductions' => (float) ($prev?->other_deductions ?? 0),
                ]);

                $computed['payroll_period_id'] = $period->id;
                $computed['employee_id'] = $employee->id;
                $computed['status'] = PayrollItem::STATUS_DRAFT;
                $computed['computation_json'] = $computed['trace'];

                PayrollItem::create(collect($computed)->except(['trace', 'income_lines', 'deduction_lines'])->all());
                $count++;
            }
        });

        Audit::record('generated', $period, [], ['items' => $count]);

        return ActionResult::ok("Payroll computed for {$count} employee(s).");
    }
}
