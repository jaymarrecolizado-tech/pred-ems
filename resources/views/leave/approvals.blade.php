@extends('layouts.app')

@section('title', 'Leave Approvals')

@section('content')
    <form method="GET" action="{{ route('leave.approvals') }}" class="filter-bar">
        <div class="field">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Name or employee no.">
        </div>
        <div class="field" style="flex:0 0 180px">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-actions" style="margin:0">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('leave.approvals') }}" class="btn btn-outline">Clear</a>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h2>Leave Applications <span class="hint">({{ $applications->total() }} record(s))</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
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
                            <td>
                                <div class="employee-cell">
                                    @include('partials.avatar', ['employee' => $application->employee, 'size' => 34])
                                    <div>
                                        <div class="name">{{ $application->employee->full_name }}</div>
                                        <div class="num">{{ $application->employee->employee_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-blue">{{ $application->leaveType->name }}</span></td>
                            <td>
                                <div style="font-weight:600">{{ $application->date_from->format('M d, Y') }} → {{ $application->date_to->format('M d, Y') }}</div>
                                @if ($application->reason)
                                    <div class="text-muted" style="font-size:12px">{{ \Illuminate\Support\Str::limit($application->reason, 55) }}</div>
                                @endif
                                @if ($application->status === 'rejected' && $application->denial_reason)
                                    <div class="diff-new" style="font-size:12px">Rejected: {{ $application->denial_reason }}</div>
                                @endif
                            </td>
                            <td class="num"><strong>{{ number_format($application->days_applied, 2) }}</strong></td>
                            <td><span class="badge {{ $application->status_badge }}">{{ $application->status_label }}</span></td>
                            <td class="actions">
                                @if ($application->isPending())
                                    <form method="POST" action="{{ route('leave.approve', $application) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-outline btn-sm" data-reject-target="{{ $application->id }}">Reject</button>
                                    <form id="reject-form-{{ $application->id }}" method="POST" action="{{ route('leave.reject', $application) }}" class="inline" style="display:none">
                                        @csrf
                                        <input type="text" name="denial_reason" placeholder="Reason for rejection" required maxlength="255" style="width:180px; padding:6px 10px; border:1px solid var(--line-strong); border-radius:var(--radius-control)">
                                        <button type="submit" class="btn btn-danger btn-sm">Confirm</button>
                                    </form>
                                @else
                                    <span class="text-muted">{{ $application->approver?->name ?? '—' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">No leave applications match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">
            {{ $applications->links('vendor.pagination.custom') }}
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-reject-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById('reject-form-' + btn.dataset.rejectTarget);
                if (form) form.style.display = form.style.display === 'none' ? 'inline-flex' : 'none';
            });
        });
    </script>
@endsection
