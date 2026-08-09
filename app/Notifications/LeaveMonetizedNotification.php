<?php

namespace App\Notifications;

use App\Models\LeaveMonetization;

class LeaveMonetizedNotification extends HrisNotification
{
    public function __construct(LeaveMonetization $monetization)
    {
        parent::__construct(
            title: 'VL monetization processed',
            body: 'Your vacation leave monetization of ' . number_format($monetization->days, 2)
                . ' day(s) (₱' . number_format($monetization->gross_amount, 2)
                . ') has been processed. Reference: ' . $monetization->reference_no . '.',
            url: route('leave.index'),
            smsText: 'Your VL monetization (' . number_format($monetization->days, 2)
                . ' days, ₱' . number_format($monetization->gross_amount, 2) . ') has been processed.',
        );
    }
}
