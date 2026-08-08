<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * In-system notification inbox + read/unread actions for the bell.
 */
class NotificationController extends Controller
{
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
}
