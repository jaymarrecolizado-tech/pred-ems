@extends('layouts.app')

@section('title', 'Attrition Report')

@section('content')
    <form method="GET" action="{{ route('reports.attrition') }}" class="filter-bar">
        <div class="field" style="flex:0 0 180px">
            <label for="year">Year</label>
            <select id="year" name="year">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-actions" style="margin:0">
            <button type="submit" class="btn btn-primary">Run Report</button>
            <a href="{{ route('reports.attrition', ['format' => 'csv', 'year' => $year]) }}" class="btn btn-outline">Export CSV</a>
        </div>
    </form>

    <div class="info-grid" style="grid-template-columns:1fr 1fr; align-items:start">
        <div class="card">
            <div class="card-header">
                <h2>Separated / Retired — {{ $year }} <span class="hint">({{ $separated->count() }} · by last record update)</span></h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Position</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($separated as $employee)
                            <tr>
                                <td>
                                    <div style="font-weight:600">{{ $employee->full_name }}</div>
                                    <div class="num" style="font-size:11.5px; color:var(--ink-400)">{{ $employee->employee_number }}</div>
                                </td>
                                <td><span class="badge badge-red">{{ $employee->status_label }}</span></td>
                                <td>{{ $employee->position?->title ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No separations in {{ $year }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>New Hires — {{ $year }} <span class="hint">({{ $hired->count() }})</span></h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Position</th>
                            <th>Appointment Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($hired as $employee)
                            <tr>
                                <td>
                                    <div style="font-weight:600">{{ $employee->full_name }}</div>
                                    <div class="num" style="font-size:11.5px; color:var(--ink-400)">{{ $employee->employee_number }}</div>
                                </td>
                                <td>{{ $employee->position?->title ?? '—' }}</td>
                                <td style="white-space:nowrap">{{ $employee->date_original_appointment?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No new hires with appointments in {{ $year }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
