<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Notifications\LeaveFiledNotification;
use App\Support\Audit;
use App\Support\Format;
use App\Support\Notifier;
use Carbon\Carbon;

/**
 * File a leave application for an employee: compute working days, verify the
 * credit balance for ledger-managed types, persist the application, audit it
 * and alert the approval reviewers.
 */
class FileLeaveApplication
{
    public function handle(Employee $employee, array $validated): ActionResult
    {
        $from = Carbon::parse($validated['date_from']);
        $to = Carbon::parse($validated['date_to']);
        $days = $this->workingDays($from, $to);

        $type = LeaveType::findOrFail($validated['leave_type_id']);
        if (! $type->is_active) {
            return ActionResult::fail('This leave type is not available.');
        }

        // Ledger-managed leaves (monthly accrual like VL/SL, or annual grants
        // like SLP) require a sufficient credit balance. Statutory leaves
        // (maternity, paternity, …) are granted per occurrence and skip this.
        if ($type->accrual_per_month > 0 || $type->annual_grant) {
            $balance = $employee->leaveBalanceFor($type);
            if ($balance < $days) {
                return ActionResult::fail(
                    "Insufficient {$type->code} balance: you have ".Format::days($balance)
                    .' day(s) but filed '.Format::days($days).'.'
                );
            }
        }

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'days_applied' => $days,
            'reason' => $validated['reason'],
            'contact_during_leave' => $validated['contact_during_leave'] ?? null,
            'commutation_requested' => $validated['commutation_requested'] ?? false,
            'status' => 'pending',
        ]);

        Audit::record('created', $application, [], $application->toArray());

        // Alert the approval reviewers.
        Notifier::send(Notifier::hrUsers(), new LeaveFiledNotification($application));

        return ActionResult::ok(
            'Leave application filed: '.Format::days($days)." day(s) of {$type->name} ({$from->format('M d')} – {$to->format('M d, Y')}).",
            $application
        );
    }

    /**
     * Working days (Mon–Fri) between two dates, inclusive.
     */
    private function workingDays(Carbon $from, Carbon $to): float
    {
        $days = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if ($d->isWeekday()) {
                $days++;
            }
        }

        return $days;
    }
}
