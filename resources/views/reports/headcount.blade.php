@extends('layouts.app')

@section('title', 'Headcount Report')

@section('content')
    <form method="GET" action="{{ route('reports.headcount') }}" class="filter-bar">
        <div class="field" style="flex:0 0 240px">
            <label for="group">Group by</label>
            <select id="group" name="group">
                <option value="employment_type" @selected($group === 'employment_type')>Employment Type</option>
                <option value="division" @selected($group === 'division')>Division</option>
                <option value="status" @selected($group === 'status')>Status</option>
                <option value="source_of_fund" @selected($group === 'source_of_fund')>Source of Fund</option>
            </select>
        </div>
        <div class="field" style="flex:0 0 200px">
            <label for="status">Status</label>
            <select id="status" name="status">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-actions" style="margin:0">
            <button type="submit" class="btn btn-primary">Run Report</button>
            <a href="{{ route('reports.headcount', ['format' => 'csv', 'group' => $group, 'status' => $status]) }}" class="btn btn-outline">Export CSV</a>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h2>Headcount by {{ $groupLabel }} <span class="hint">({{ $total }} employees)</span></h2>
        </div>
        <div class="card-pad">
            <div class="chart-body" style="padding:0">
                <div class="grow-list">
                    @foreach ($rows as $row)
                        <li>
                            <span>{{ $row['label'] }}</span>
                            <span style="display:flex; align-items:center; gap:12px">
                                <span class="progress" style="width:140px"><i style="width:{{ $row['pct'] }}%"></i></span>
                                <strong style="font-variant-numeric:tabular-nums">{{ $row['count'] }}</strong>
                                <span class="num" style="font-size:12px; color:var(--ink-400)">{{ $row['pct'] }}%</span>
                            </span>
                        </li>
                    @endforeach
                </div>
                @if ($rows->isEmpty())
                    <div class="empty" style="color:var(--ink-400); font-size:13px; padding:24px 0; text-align:center">No employees match these filters.</div>
                @endif
            </div>
        </div>
    </div>
@endsection
