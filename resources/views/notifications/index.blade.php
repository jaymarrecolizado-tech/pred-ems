@extends('layouts.app')

@section('title', 'Notifications')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Notifications']]])
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h2>Notifications <span class="hint">({{ $notifications->total() }})</span></h2>
            <div style="display:flex; gap:6px; flex-wrap:wrap">
                <a href="{{ route('notifications.index', ['filter' => 'all']) }}" class="btn btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-outline' }}">All</a>
                <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="btn btn-sm {{ $filter === 'unread' ? 'btn-primary' : 'btn-outline' }}">Unread</a>
                <form method="POST" action="{{ route('notifications.read-all') }}" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">Mark all read</button>
                </form>
            </div>
        </div>
        <div class="grow-list" style="padding:6px 0">
            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $unread = ! $notification->read_at;
                @endphp
                <li class="notif-row {{ $unread ? 'unread' : '' }}">
                    <div style="min-width:0; flex:1">
                        <div style="font-weight:600; display:flex; align-items:center; gap:8px">
                            @if ($unread)<span class="notif-dot" aria-hidden="true"></span>@endif
                            {{ $data['title'] ?? 'Notification' }}
                        </div>
                        <div style="font-size:13px; color:var(--ink-500); margin-top:2px">{{ $data['body'] ?? '' }}</div>
                        <div class="num" style="font-size:11px; color:var(--ink-400); margin-top:3px">
                            {{ $notification->created_at->format('M j, Y g:i A') }} · {{ $notification->created_at->diffForHumans() }}
                        </div>
                    </div>
                    <div style="display:flex; gap:6px; flex-shrink:0; align-items:center">
                        @if (! empty($data['url']))
                            <a href="{{ $data['url'] }}" class="btn btn-outline btn-sm">Open</a>
                        @endif
                        @if ($unread)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm">Mark read</button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="text-muted" style="padding:24px 18px">
                    {{ $filter === 'unread' ? 'No unread notifications — you are all caught up.' : 'No notifications yet. Alerts about document requests, leave approvals, and payroll will appear here.' }}
                </li>
            @endforelse
        </div>
        <div class="card-pad">
            {{ $notifications->links() }}
        </div>
    </div>
@endsection
