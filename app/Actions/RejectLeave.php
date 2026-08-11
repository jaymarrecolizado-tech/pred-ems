<?php

namespace App\Actions;

use App\Models\LeaveApplication;
use App\Notifications\LeaveRejectedNotification;
use App\Support\Audit;
use App\Support\Notifier;

/**
 * Reject a pending leave application with the HR denial reason, audit it and
 * notify the employee.
 */
class RejectLeave
{
    public function handle(LeaveApplication $application, string $denialReason): ActionResult
    {
        $old = $application->toArray();
        $application->update([
            'status' => 'rejected',
            'approver_id' => auth()->id(),
            'denial_reason' => $denialReason,
        ]);

        Audit::record('rejected', $application, $old, $application->toArray());

        if ($application->employee->user) {
            Notifier::send($application->employee->user, new LeaveRejectedNotification($application));
        }

        return ActionResult::ok('Leave application rejected for '.$application->employee->full_name.'.');
    }
}
