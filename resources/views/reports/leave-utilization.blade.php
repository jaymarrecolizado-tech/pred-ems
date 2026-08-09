@extends('layouts.app')

@section('title', 'Leave Utilization Report')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Reports', route('reports.index')], ['Leave Utilization']]])
@endsection

@section('content')
    <form method="GET" action="{{ route('reports.leave-utilization') }}" class="filter-bar">
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
            @include('partials.report-exports', [
                'route' => 'reports.leave-utilization',
                'params' => ['year' => $year],
            ])
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h2>Leave Utilization — {{ $year }} <span class="hint">({{ number_format($totalDays, 2) }} approved days taken)</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th class="num">Approved Applications</th>
                        <th class="num">Employees</th>
                        <th class="num">Days Taken</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <div style="font-weight:600">{{ $row['leave_type']->name }}</div>
                                <div class="num" style="font-size:11.5px; color:var(--ink-400)">{{ $row['leave_type']->code }}</div>
                            </td>
                            <td class="num">{{ $row['applications'] }}</td>
                            <td class="num">{{ $row['employees'] }}</td>
                            <td class="num"><strong>{{ number_format($row['days'], 2) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No approved leave applications for {{ $year }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
