@extends('layouts.app')

@section('title', 'Attendance Corrections')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Correction Requests']]])
@endsection

@section('content')
    <form method="GET" action="{{ route('attendance.corrections') }}" class="filter-bar">
        <div class="field" style="flex:0 0 200px">
            <label for="status">Status</label>
            <select id="status" name="status">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-actions" style="margin:0">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h2>Correction Requests <span class="hint">({{ $corrections->total() }})</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Punch</th>
                        <th>Requested Time</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="num">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($corrections as $correction)
                        <tr>
                            <td>
                                <div style="font-weight:600">{{ $correction->employee?->full_name ?? '—' }}</div>
                                <div class="num" style="font-size:11.5px; color:var(--ink-400)">{{ $correction->employee?->employee_number }}</div>
                            </td>
                            <td>{{ $correction->log_date->format('M d, Y') }}</td>
                            <td><span class="badge badge-blue">{{ $correction->punch_type_label }}</span></td>
                            <td class="num"><strong>{{ \Illuminate\Support\Carbon::parse($correction->requested_time)->format('h:i A') }}</strong></td>
                            <td style="max-width:240px">{{ $correction->reason }}</td>
                            <td><span class="badge {{ $correction->status_badge }}">{{ $correction->status }}</span></td>
                            <td class="num">
                                @if ($correction->isPending())
                                    <div style="display:flex; gap:6px; justify-content:flex-end">
                                        <form method="POST" action="{{ route('attendance.corrections.approve', $correction) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm" style="background:#15803d; color:#fff">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('attendance.corrections.reject', $correction) }}" id="reject-{{ $correction->id }}">
                                            @csrf
                                            <input type="hidden" name="denial_reason" value="Reviewed by HR">
                                            <button type="button" class="btn btn-danger btn-sm" onclick="promptReject({{ $correction->id }})">Reject</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted">{{ $correction->reviewed_at?->format('M d, Y') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No correction requests{{ $status !== 'all' ? " with status \"$status\"" : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">
            {{ $corrections->links('vendor.pagination.custom') }}
        </div>
    </div>
@endsection

@push('scripts')
<script>
function promptReject(id) {
    const reason = window.prompt('Reason for rejection:');
    if (reason === null) return;
    const form = document.getElementById('reject-' + id);
    form.querySelector('[name=denial_reason]').value = reason;
    form.submit();
}
</script>
@endpush
