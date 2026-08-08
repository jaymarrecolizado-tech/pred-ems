@extends('layouts.app')

@section('title', 'My Leave')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['My Leave']]])
@endsection

@section('content')
    @if (! $employee)
        <div class="card card-pad">
            <h2 style="margin:0 0 8px">No employee record linked</h2>
            <p class="text-muted" style="margin:0">Your login is not yet connected to an employee 201-file profile. Contact the HR office so they can link your account before you can file leave.</p>
        </div>
    @else
        <div class="overline">Leave Balances</div>
        <div class="stat-row">
            @forelse ($balances as $row)
                <div class="stat-tile">
                    <span class="chip {{ $loop->first ? 'blue' : ($loop->iteration % 2 ? 'emerald' : 'indigo') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </span>
                    <span>
                        <div class="stat-tile-label">{{ $row->leave_type->name }}</div>
                        <div class="stat-tile-value">{{ number_format($row->balance, 2) }} <span style="font-size:12px;color:var(--ink-400);font-weight:500">days</span></div>
                    </span>
                </div>
            @empty
                <div class="stat-tile">
                    <span>
                        <div class="stat-tile-label">No credits yet</div>
                        <div class="stat-tile-value">0.00</div>
                    </span>
                </div>
            @endforelse
        </div>

        <div class="card">
            <div class="card-header">
                <h2>My Leave Applications</h2>
                <a href="{{ route('leave.create') }}" class="btn btn-primary btn-sm">+ File Leave</a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Dates</th>
                            <th class="num">Days</th>
                            <th>Status</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($applications as $application)
                            <tr>
                                <td><span class="badge badge-blue">{{ $application->leaveType->name }}</span></td>
                                <td>
                                    <div style="font-weight:600">{{ $application->date_from->format('M d, Y') }} → {{ $application->date_to->format('M d, Y') }}</div>
                                    @if ($application->reason)
                                        <div class="text-muted" style="font-size:12px">{{ \Illuminate\Support\Str::limit($application->reason, 60) }}</div>
                                    @endif
                                </td>
                                <td class="num"><strong>{{ number_format($application->days_applied, 2) }}</strong></td>
                                <td><span class="badge {{ $application->status_badge }}">{{ $application->status_label }}</span></td>
                                <td class="actions">
                                    @if ($application->isPending())
                                        <form method="POST" action="{{ route('leave.cancel', $application) }}" class="inline"
                                              onsubmit="return confirm('Cancel this leave application?')">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm">Cancel</button>
                                        </form>
                                    @elseif ($application->status === 'rejected' && $application->denial_reason)
                                        <span class="text-muted" title="{{ $application->denial_reason }}">Reason: {{ \Illuminate\Support\Str::limit($application->denial_reason, 28) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No leave applications yet. <a href="{{ route('leave.create') }}">File your first leave</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($applications)
                <div class="pagination">{{ $applications->links('vendor.pagination.custom') }}</div>
            @endif
        </div>
    @endif
@endsection
