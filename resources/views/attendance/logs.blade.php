@extends('layouts.app')

@section('title', 'Attendance Timelogs')

@section('content')
    <form method="GET" action="{{ route('attendance.logs') }}" class="filter-bar">
        <div class="field">
            <label for="employee_id">Employee</label>
            <select id="employee_id" name="employee_id">
                <option value="">All employees</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->full_name }} ({{ $employee->employee_number }})</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="flex:0 0 180px">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" value="{{ request('date') }}">
        </div>
        <div class="field" style="flex:0 0 160px">
            <label for="punch_type">Punch</label>
            <select id="punch_type" name="punch_type">
                <option value="">All punches</option>
                @foreach ($punchTypes as $value => $label)
                    <option value="{{ $value }}" @selected(request('punch_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-actions" style="margin:0">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('attendance.logs') }}" class="btn btn-outline">Clear</a>
        </div>
    </form>

    <div class="info-grid" style="grid-template-columns:1fr 2fr; align-items:start">
        {{-- Manual HR punch --}}
        <div class="card">
            <div class="card-header">
                <h2>Manual HR Entry</h2>
            </div>
            <div class="card-pad">
                <p class="hint" style="font-size:12px; margin-top:0">
                    For employees outside the geofence (field duty, forgotten punch, etc.). Every entry is audited.
                </p>
                <form method="POST" action="{{ route('attendance.logs.store') }}" class="form-grid">
                    @csrf
                    <div class="field" style="grid-column:1 / -1">
                        <label for="emp_id">Employee <span class="req">*</span></label>
                        <select id="emp_id" name="employee_id" required>
                            <option value="">— Select —</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }} ({{ $employee->employee_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="log_date">Date</label>
                        <input type="date" id="log_date" name="log_date" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="field">
                        <label for="manual_punch_type">Punch</label>
                        <select id="manual_punch_type" name="punch_type" required>
                            @foreach ($punchTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="grid-column:1 / -1">
                        <label for="time">Time</label>
                        <input type="time" id="time" name="time" required>
                    </div>
                    <div class="field" style="grid-column:1 / -1">
                        <label for="remarks">Remarks <span class="req">*</span></label>
                        <textarea id="remarks" name="remarks" rows="2" placeholder="e.g. Official travel, GPS failure, forgot to punch" required></textarea>
                    </div>
                    <div style="grid-column:1 / -1">
                        <button type="submit" class="btn btn-primary">Record Punch</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Logs table --}}
        <div class="card">
            <div class="card-header">
                <h2>Timelog <span class="hint">({{ $logs->total() }} punches)</span></h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Punch</th>
                            <th class="num">Time</th>
                            <th>Checkpoint</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>
                                    <div style="font-weight:600">{{ $log->employee?->full_name ?? '—' }}</div>
                                    <div class="num" style="font-size:11.5px; color:var(--ink-400)">{{ $log->employee?->employee_number }}</div>
                                </td>
                                <td>{{ $log->log_date->format('M d, Y') }}</td>
                                <td><span class="badge badge-blue">{{ $log->punch_type_label }}</span></td>
                                <td class="num"><strong>{{ $log->time }}</strong></td>
                                <td>{{ $log->checkpoint?->name ?? ($log->source === 'hr_manual' || $log->source === 'hr_correction' ? 'HR-entered' : '—') }}</td>
                                <td>
                                    @php
                                        $badge = match ($log->source) {
                                            'hr_manual', 'hr_correction' => 'badge-amber',
                                            default => 'badge-green',
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ str_replace('_', ' ', $log->source) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">No punches match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                {{ $logs->links('vendor.pagination.custom') }}
            </div>
        </div>
    </div>
@endsection
