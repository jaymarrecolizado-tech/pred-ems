<?php

namespace App\Actions;

use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Remittance;
use App\Support\Audit;
use App\Support\Payroll;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Finalize a draft payroll period: lock every item, issue one payslip per
 * item and aggregate the per-agency remittance summaries. Irreversible —
 * corrections go through a reversal/adjustment period.
 */
class FinalizePayroll
{
    public function handle(PayrollPeriod $period): ActionResult
    {
        $period->load('items');
        if ($period->items->isEmpty()) {
            $message = 'Compute the payroll before finalizing.';

            return ActionResult::fail($message, ['items' => $message]);
        }

        DB::transaction(function () use ($period) {
            $employerTotals = ['GSIS' => 0.0, 'PHILHEALTH' => 0.0, 'PAGIBIG' => 0.0];

            foreach ($period->items as $item) {
                $item->update(['status' => PayrollItem::STATUS_FINALIZED]);

                $trace = $item->computation_json ?? [];
                $summary = $trace['summary'] ?? [];
                $employerTotals['GSIS'] += (float) ($summary['gsis_er'] ?? 0);
                $employerTotals['PHILHEALTH'] += (float) ($summary['philhealth_er'] ?? 0);
                $employerTotals['PAGIBIG'] += (float) ($summary['pagibig_er'] ?? 0);

                for ($attempt = 0; $attempt < 5; $attempt++) {
                    $ref = Payroll::nextPayslipRef();
                    try {
                        Payslip::create([
                            'payroll_item_id' => $item->id,
                            'reference_no' => $ref,
                            'generated_by' => auth()->id(),
                            'generated_at' => now(),
                        ]);
                        break;
                    } catch (QueryException $e) {
                        if ((int) $e->errorInfo[1] !== 1062) {
                            throw $e;
                        }
                    }
                }
            }

            // Aggregate remittance summaries per agency.
            $from = $period->period_from->toDateString();
            $to = $period->period_to->toDateString();
            $remittanceRows = [
                'GSIS' => (object) ['ee' => $period->items->sum(fn ($i) => (float) $i->gsis_employee_share), 'er' => $employerTotals['GSIS']],
                'PHILHEALTH' => (object) ['ee' => $period->items->sum(fn ($i) => (float) $i->philhealth_employee_share), 'er' => $employerTotals['PHILHEALTH']],
                'PAGIBIG' => (object) ['ee' => $period->items->sum(fn ($i) => (float) $i->pagibig_employee_share), 'er' => $employerTotals['PAGIBIG']],
                'BIR' => (object) ['ee' => $period->items->sum(fn ($i) => (float) $i->withholding_tax), 'er' => 0.0],
            ];

            foreach ($remittanceRows as $agency => $row) {
                Remittance::updateOrCreate(
                    ['agency' => $agency, 'period_from' => $from, 'period_to' => $to],
                    [
                        'employee_share_total' => $row->ee,
                        'employer_share_total' => $row->er,
                        'grand_total' => $row->ee + $row->er,
                        'status' => Remittance::STATUS_PENDING,
                        'remarks' => 'Generated on payroll finalization',
                    ]
                );
            }

            $period->update([
                'status' => PayrollPeriod::STATUS_FINALIZED,
                'finalized_by' => auth()->id(),
                'finalized_at' => now(),
            ]);
        });

        Audit::record('finalized', $period, [], ['status' => PayrollPeriod::STATUS_FINALIZED]);

        return ActionResult::ok('Period finalized — payslips and remittance summaries issued.');
    }
}
