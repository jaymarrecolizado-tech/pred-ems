@extends('layouts.app')

@section('title', 'Audit Trail')

@section('content')
    <form method="GET" action="{{ route('audit-logs.index') }}" class="filter-bar">
        <div class="field">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Actor, email, record #, IP">
        </div>
        <div class="field" style="flex:0 0 180px">
            <label for="action">Action</label>
            <select id="action" name="action">
                <option value="">All actions</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ ucfirst(str_replace('_', ' ', $action)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-actions" style="margin:0">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('audit-logs.index') }}" class="btn btn-outline">Clear</a>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h2>Activity Log <span class="hint">(append-only · {{ $logs->total() }} entr{{ $logs->total() === 1 ? 'y' : 'ies' }})</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Record</th>
                        <th>Changes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td style="white-space:nowrap">
                                <div style="font-weight:600">{{ $log->created_at->format('M d, Y') }}</div>
                                <div class="num" style="font-size:11.5px; color:var(--ink-400)">{{ $log->created_at->format('h:i A') }}</div>
                            </td>
                            <td>
                                <div style="font-weight:600">{{ $log->user?->name ?? 'System' }}</div>
                                <div class="num" style="font-size:11.5px; color:var(--ink-400)">{{ $log->user?->email }}</div>
                            </td>
                            <td>
                                @php
                                    $badge = match ($log->action) {
                                        'created' => 'badge-green',
                                        'deleted' => 'badge-red',
                                        'profile_updated', 'updated' => 'badge-blue',
                                        'photo_updated', 'photo_removed' => 'badge-purple',
                                        'password_changed' => 'badge-amber',
                                        default => 'badge-gray',
                                    };
                                @endphp
                                <span class="badge {{ $badge }}">{{ $log->action_label }}</span>
                            </td>
                            <td>
                                <div>{{ $log->model_label }}</div>
                                <div class="num" style="font-size:11.5px; color:var(--ink-400)">#{{ $log->model_id ?? '—' }}</div>
                            </td>
                            <td class="diff-cell">
                                @php
                                    $old = $log->old_values ?? [];
                                    $new = $log->new_values ?? [];
                                    $pairs = [];
                                    foreach ($new as $key => $value) {
                                        if (in_array($key, ['created_at', 'updated_at'])) continue;
                                        $oldValue = $old[$key] ?? null;
                                        $newValue = $value;
                                        if ($oldValue == $newValue && $oldValue !== null) continue;
                                        $pairs[] = [$key, $oldValue, $newValue];
                                    }
                                    $pairs = array_slice($pairs, 0, 4);
                                @endphp
                                @if ($pairs)
                                    @foreach ($pairs as [$key, $oldValue, $newValue])
                                        <div class="diff-row">
                                            <code>{{ $key }}</code>
                                            @if ($oldValue !== null)
                                                <span class="diff-old">{{ is_scalar($oldValue) ? str()->limit((string) $oldValue, 24) : '…' }}</span>
                                                <span class="diff-arrow">→</span>
                                            @endif
                                            <span class="diff-new">{{ is_scalar($newValue) ? str()->limit((string) $newValue, 24) : '…' }}</span>
                                        </div>
                                    @endforeach
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">No audit entries yet. Actions will appear here as records are created and changed.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">
            {{ $logs->links('vendor.pagination.custom') }}
        </div>
    </div>
@endsection
