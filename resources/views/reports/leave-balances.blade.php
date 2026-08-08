@extends('layouts.app')

@section('title', 'Leave Balances Report')

@section('content')
    <div class="filter-bar" style="justify-content:flex-end">
        <div class="form-actions" style="margin:0">
            <a href="{{ route('reports.leave-balances', ['format' => 'csv']) }}" class="btn btn-outline">Export CSV</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Leave Balances <span class="hint">({{ $totalActive }} active employees · derived from the credit ledger)</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        @foreach ($leaveTypes as $type)
                            <th class="num">{{ $type->code }} Balance (days)</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <div style="font-weight:600">{{ $row['employee']->full_name }}</div>
                                <div class="num" style="font-size:11.5px; color:var(--ink-400)">{{ $row['employee']->employee_number }} · {{ $row['employee']->employmentType?->name ?? '—' }}</div>
                            </td>
                            @foreach ($leaveTypes as $type)
                                <td class="num">
                                    <strong style="color:{{ $row['balances'][$type->code] < 0 ? 'var(--red-text)' : 'inherit' }}">{{ number_format($row['balances'][$type->code], 2) }}</strong>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ $leaveTypes->count() + 1 }}" class="text-muted">No active employees.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
