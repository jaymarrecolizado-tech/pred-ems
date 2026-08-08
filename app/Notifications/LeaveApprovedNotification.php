<?php

namespace App\Notifications;

use App\Models\LeaveApplication;

class LeaveApprovedNotification extends HrisNotification
{
    public function __construct(LeaveApplication $application)
    {
        $dates = $application->date_from->format('M d') . ' – ' . $application->date_to->format('M d, Y');

        parent::__construct(
            title: 'Leave approved',
            body: "Your leave application of {$application->days_applied} day(s) of {$application->leaveType->name} ({$dates}) has been approved.",
            url: route('leave.index'),
            smsText: "Your leave ({$application->days_applied} day(s), {$dates}) has been approved.",
        );
    }
}
