@extends('layouts.app')

@section('title', 'Attendance Summary')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Reports', route('reports.index')], ['Attendance Summary']]])
@endsection

@section('content')
    <div class="overline">Attendance & DTR</div>

    <form method="GET" action="{{ route('reports.attendance-summary') }}" class="filter-bar">
        <div class="field" style="flex:0 0 120px">
            <label for="month">Month</label>
            <select id="month" name="month">
                @foreach ($months as $m)
                    <option value="{{ $m }}" @selected($month === $m)>{{ Carbon\Carbon::create(null, $m)->format('F') }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="flex:0 0 110px">
            <label for="year">Year</label>
            <select id="year" name="year">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="flex:0 0 220px">
            <label for="division">Division</label>
            <select id="division" name="division">
                <option value="">All divisions</option>
                @foreach ($divisions as $d)
                    <option value="{{ $d->id }}" @selected($divisionId === $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-actions" style="margin:0">
            <button type="submit" class="btn btn-primary">Run Report</button>
            @include('partials.report-exports', [
                'route' => 'reports.attendance-summary',
                'params' => array_filter(['month' => $month, 'year' => $year, 'division' => $divisionId]),
            ])
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h2>Attendance Summary — {{ $monthLabel }} <span class="hint">({{ $rows->count() }} active employees)</span></h2>
        </div>
        <div class="card-pad" style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee No</th>
                        <th>Name</th>
                        <th>Division</th>
                        <th class="num">Work Days</th>
                        <th class="num">Days Present</th>
                        <th class="num">Absences</th>
                        <th class="num">Hours</th>
                        <th class="num">Late (min)</th>
                        <th class="num">Undertime (min)</th>
                        <th class="num">Rest-Day/OT (hrs)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['employee']->employee_number }}</td>
                            <td><a href="{{ route('employees.show', $row['employee']) }}">{{ $row['employee']->full_name }}</a></td>
                            <td>{{ $row['employee']->division?->name ?? '—' }}</td>
                            <td class="num">{{ $row['workdays'] }}</td>
                            <td class="num">{{ $row['present'] }}</td>
                            <td class="num {{ $row['absences'] > 0 ? 'text-danger' : '' }}">{{ $row['absences'] }}</td>
                            <td class="num">{{ number_format($row['hours'], 2) }}</td>
                            <td class="num {{ $row['late'] > 0 ? 'text-danger' : '' }}">{{ $row['late'] }}</td>
                            <td class="num {{ $row['undertime'] > 0 ? 'text-danger' : '' }}">{{ $row['undertime'] }}</td>
                            <td class="num">{{ number_format($row['ot_hours'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-muted">No active employees match these filters for {{ $monthLabel }}.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th colspan="3">Totals ({{ $rows->count() }} employees)</th>
                            <th class="num">{{ $totals['workdays'] }}</th>
                            <th class="num">{{ $totals['present'] }}</th>
                            <th class="num">{{ $totals['absences'] }}</th>
                            <th class="num">{{ number_format($totals['hours'], 2) }}</th>
                            <th class="num">{{ $totals['late'] }}</th>
                            <th class="num">{{ $totals['undertime'] }}</th>
                            <th class="num">{{ number_format($totals['ot_hours'], 2) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="card-pad" style="border-top:1px solid var(--line); padding-top:12px">
            <p class="hint" style="margin:0">
                <strong>Work Days</strong> = scheduled working days in {{ $monthLabel }} (holidays excluded) · <strong>Absences</strong> = work days without any punch ·
                <strong>Rest-Day/OT</strong> = hours rendered on rest days, weekends &amp; holidays — the evidence for CTO credit claims.
                Late &amp; undertime are in minutes. Totals row reflects every row shown (not per-capita averages).
            </p>
        </div>
    </div>
@endsection
