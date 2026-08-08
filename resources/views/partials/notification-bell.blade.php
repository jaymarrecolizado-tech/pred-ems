@php
    $unreadCount = auth()->user()->unreadNotifications()->count();
    $latestNotifications = auth()->user()->notifications()->latest()->take(6)->get();
@endphp
<details class="notif-dd" id="notif-dd">
    <summary class="btn-icon notif-summary" aria-label="{{ $unreadCount > 0 ? $unreadCount . ' unread notifications' : 'Notifications' }}">
        <svg class="btn-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        @if ($unreadCount > 0)
            <span class="notif-badge">{{ min($unreadCount, 99) }}</span>
        @endif
    </summary>
    <div class="notif-dd-panel">
        <div class="notif-dd-head">
            <strong>Notifications</strong>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">Mark all read</button>
                </form>
            @endif
        </div>
        @forelse ($latestNotifications as $notification)
            @php $data = $notification->data; @endphp
            <a href="{{ $data['url'] ?? route('notifications.index') }}"
               class="notif-dd-item {{ $notification->read_at ? 'is-read' : '' }}"
               data-id="{{ $notification->id }}"
               data-url="{{ $data['url'] ?? route('notifications.index') }}">
                <div class="notif-dd-title">
                    @if (! $notification->read_at)<span class="notif-dot" aria-hidden="true"></span>@endif
                    {{ $data['title'] ?? 'Notification' }}
                </div>
                <div class="notif-dd-body">{{ \Illuminate\Support\Str::limit($data['body'] ?? '', 90) }}</div>
                <div class="notif-dd-time">{{ $notification->created_at->diffForHumans() }}</div>
            </a>
        @empty
            <div class="notif-dd-empty">You're all caught up 🎉</div>
        @endforelse
        <a href="{{ route('notifications.index') }}" class="notif-dd-all">View all notifications</a>
    </div>
</details>
