<?php

namespace App\Notifications\Channels;

use App\Models\Setting;
use App\Notifications\HrisNotification;
use App\Support\Sms;
use Illuminate\Notifications\Notification;

/**
 * Custom Laravel notification channel: enqueues the SMS body to the
 * `sms_queue` table for the async gateway worker. No-op when SMS is disabled
 * (env or the admin's SMS toggle), or the recipient has no phone on file —
 * never throws.
 */
class SmsChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! Setting::smsNotificationsEnabled() || ! Sms::enabled()) {
            return;
        }

        $phone = $notifiable?->employee?->contact_number;
        if (! $phone) {
            return;
        }

        $text = method_exists($notification, 'toSms') ? $notification->toSms($notifiable) : null;
        if (! $text) {
            return;
        }

        Sms::enqueue($phone, $text);
    }
}
