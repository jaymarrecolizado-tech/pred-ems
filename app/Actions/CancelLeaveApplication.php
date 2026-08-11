<?php

namespace App\Actions;

use App\Models\LeaveApplication;
use App\Support\Audit;

/**
 * Cancel a pending leave application (employee-initiated). State guards are
 * the controller's responsibility; this applies the change + audit entry.
 */
class CancelLeaveApplication
{
    public function handle(LeaveApplication $application): ActionResult
    {
        $old = $application->toArray();
        $application->update(['status' => 'cancelled']);
        Audit::record('updated', $application, $old, $application->toArray());

        return ActionResult::ok('Leave application cancelled.');
    }
}
