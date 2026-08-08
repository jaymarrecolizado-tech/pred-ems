<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification dispatch that must never break the business action that
 * triggered it: any channel failure (e.g. a broken SMTP relay) is logged and
 * swallowed, while the in-system inbox row is still created by the database
 * channel.
 */
class Notifier
{
    public static function send(mixed $notifiables, Notification $notification): void
    {
        try {
            \Illuminate\Support\Facades\Notification::send($notifiables, $notification);
        } catch (\Throwable $e) {
            Log::warning('Notification delivery failed', [
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The users who review queues (admin + HR) — recipients for
     * "new submission" alerts.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function hrUsers(): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'hr']))
            ->get();
    }
}
