<?php

namespace App\Notifications;

use App\Models\LeaveApplication;

class LeaveRejectedNotification extends HrisNotification
{
    public function __construct(LeaveApplication $application)
    {
        $reason = $application->denial_reason ?: 'No reason was provided.';

        parent::__construct(
            title: 'Leave application rejected',
            body: "Your leave application of {$application->days_applied} day(s) of {$application->leaveType->name} was not approved. Reason: {$reason}",
            url: route('leave.index'),
            smsText: "Your leave application was not approved. Reason: {$reason}",
        );
    }
}
