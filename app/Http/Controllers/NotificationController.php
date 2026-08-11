<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationSettingsRequest;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * In-system notification inbox + read/unread actions for the bell, and the
 * admin-controlled email/SMS channel switches.
 */
class NotificationController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Inbox */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): View
    {
        $filter = $request->string('filter', 'all');

        $notifications = auth()->user()->notifications()
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
        ]);
    }

    public function markAsRead(DatabaseNotification $notification): RedirectResponse
    {
        // Only the owner can mark their own notification as read.
        $own = auth()->user()->notifications()->where('id', $notification->id)->firstOrFail();
        $own->markAsRead();

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    /* ------------------------------------------------------------------ */
    /*  Channel switches (admin) */
    /* ------------------------------------------------------------------ */

    public function settings(): View
    {
        return view('notifications.settings', [
            'channels' => Setting::notificationChannels(),
        ]);
    }

    public function updateSettings(NotificationSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $old = Setting::notificationChannels();
        $new = [
            'email' => (bool) ($validated['email'] ?? false),
            'sms' => (bool) ($validated['sms'] ?? false),
        ];

        Setting::set('notifications.email_enabled', $new['email']);
        Setting::set('notifications.sms_enabled', $new['sms']);

        Audit::record('updated', null, $old, $new);

        return back()->with('success', 'Notification channels updated — '.
            ($new['email'] ? 'email on' : 'email off').', '.
            ($new['sms'] ? 'SMS on' : 'SMS off').'.');
    }
}
