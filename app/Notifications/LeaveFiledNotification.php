<?php

namespace App\Notifications;

use App\Models\LeaveApplication;

class LeaveFiledNotification extends HrisNotification
{
    public function __construct(LeaveApplication $application)
    {
        $dates = $application->date_from->format('M d') . ' – ' . $application->date_to->format('M d, Y');

        parent::__construct(
            title: 'New leave application',
            body: "{$application->employee->full_name} filed {$application->days_applied} day(s) of {$application->leaveType->name} ({$dates}).",
            url: route('leave.approvals'),
        );
    }
}
