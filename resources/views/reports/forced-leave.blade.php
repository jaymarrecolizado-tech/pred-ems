@extends('layouts.app')

@section('title', 'Forced Leave Monitoring')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Reports', route('reports.index')], ['Forced Leave Monitoring']]])
@endsection

@section('content')
    <form method="GET" action="{{ route('reports.forced-leave') }}" class="filter-bar">
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
                'route' => 'reports.forced-leave',
                'params' => ['year' => $year],
            ])
        </div>
    </form>

    <div class="stat-row" style="margin-bottom:18px">
        <div class="stat-tile">
            <span>
                <div class="stat-tile-label">Compliant (≥ 5 VL days taken)</div>
                <div class="stat-tile-value">{{ $compliant }}</div>
            </span>
        </div>
        <div class="stat-tile">
            <span>
                <div class="stat-tile-label" style="color:var(--red-text)">Non-compliant</div>
                <div class="stat-tile-value" style="color:var(--red-text)">{{ $nonCompliant }}</div>
            </span>
        </div>
        <div class="stat-tile">
            <span>
                <div class="stat-tile-label">Exempt (below 10 VL credits)</div>
                <div class="stat-tile-value">{{ $exempt }}</div>
            </span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Forced Leave Compliance — {{ $year }} <span class="hint">({{ $rows->count() }} active employees)</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Division</th>
                        <th class="num">VL Balance (days)</th>
                        <th class="num">VL Taken {{ $year }} (days)</th>
                        <th class="num">Required (days)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <div class="employee-cell">
                                    @include('partials.avatar', ['employee' => $row['employee'], 'size' => 34])
                                    <div>
                                        <div class="name">{{ $row['employee']->full_name }}</div>
                                        <div class="num">{{ $row['employee']->employee_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $row['employee']->division?->name ?? '—' }}</td>
                            <td class="num"><strong>{{ number_format($row['balance'], 2) }}</strong></td>
                            <td class="num">{{ number_format($row['taken'], 2) }}</td>
                            <td class="num">{{ number_format($row['required'], 2) }}</td>
                            <td>
                                @php
                                    $badge = match ($row['status']) {
                                        'compliant' => 'badge-green',
                                        'non_compliant' => 'badge-red',
                                        default => 'badge-gray',
                                    };
                                @endphp
                                <span class="badge {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $row['status'])) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">No active employees on record.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
