<?php

namespace App\Actions;

use App\Models\LeaveApplication;
use App\Models\LeaveCreditLedger;
use App\Notifications\LeaveApprovedNotification;
use App\Support\Audit;
use App\Support\Format;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * Approve a pending leave application: re-check the live credit balance so a
 * stale approval can never push the ledger negative, then atomically mark the
 * application approved and debit the append-only leave ledger.
 */
class ApproveLeave
{
    public function handle(LeaveApplication $application): ActionResult
    {
        $type = $application->leaveType;

        if ($type->accrual_per_month > 0 || $type->annual_grant) {
            $balance = $application->employee->leaveBalanceFor($type);
            if ($balance < (float) $application->days_applied) {
                return ActionResult::fail(
                    'Cannot approve: only '.Format::days($balance)
                    ." {$type->code} day(s) remain for {$application->employee->full_name}."
                );
            }
        }

        $old = $application->toArray();

        DB::transaction(function () use ($application) {
            $application->update([
                'status' => 'approved',
                'approver_id' => auth()->id(),
                'approved_at' => now(),
            ]);

            $this->debitLedger($application, 'Leave application approved');
        });

        Audit::record('approved', $application, $old, $application->toArray());

        if ($application->employee->user) {
            Notifier::send($application->employee->user, new LeaveApprovedNotification($application));
        }

        return ActionResult::ok('Leave approved for '.$application->employee->full_name.'.');
    }

    private function debitLedger(LeaveApplication $application, string $remarks): void
    {
        $balance = $application->employee->leaveBalanceFor($application->leaveType);

        LeaveCreditLedger::create([
            'employee_id' => $application->employee_id,
            'leave_type_id' => $application->leave_type_id,
            'transaction_date' => $application->date_from,
            'movement' => 'used',
            'credit' => 0,
            'debit' => $application->days_applied,
            'balance_after' => $balance - (float) $application->days_applied,
            'source_id' => $application->id,
            'source_type' => LeaveApplication::class,
            'remarks' => $remarks,
            'created_by' => auth()->id(),
        ]);
    }
}
