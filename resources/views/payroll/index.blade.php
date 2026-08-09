@extends('layouts.app')

@section('title', 'Payroll')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Payroll']]])
@endsection

@section('content')
    <div class="info-grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-bottom:18px">
        {{-- Create period --}}
        <div class="card">
            <div class="card-header">
                <h2>New Payroll Period</h2>
            </div>
            <div class="card-pad">
                <form method="POST" action="{{ route('payroll.store') }}" class="form-grid" style="grid-template-columns:1fr 1fr">
                    @csrf
                    <div class="field">
                        <label for="period_from">Period From <span class="req">*</span></label>
                        <input type="date" id="period_from" name="period_from" value="{{ old('period_from') }}" required>
                    </div>
                    <div class="field">
                        <label for="period_to">Period To <span class="req">*</span></label>
                        <input type="date" id="period_to" name="period_to" value="{{ old('period_to') }}" required>
                    </div>
                    <div class="field">
                        <label for="payroll_date">Payroll Date <span class="req">*</span></label>
                        <input type="date" id="payroll_date" name="payroll_date" value="{{ old('payroll_date') }}" required>
                    </div>
                    <div class="field" style="display:flex; align-items:flex-end; padding-bottom:8px">
                        <a href="{{ route('payroll.remittances') }}" class="btn btn-outline" style="white-space:nowrap">
                            {{ $remittancePending > 0 ? "Remittances ({$remittancePending} pending)" : 'Remittances' }}
                        </a>
                    </div>
                    <div class="field" style="grid-column:1 / -1">
                        <label for="remarks">Remarks</label>
                        <input type="text" id="remarks" name="remarks" value="{{ old('remarks') }}" placeholder="e.g. Mid-month run — August 2026">
                    </div>
                    <div style="grid-column:1 / -1" class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Period</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Active rates --}}
        <div class="card">
            <div class="card-header">
                <h2>Active Contribution &amp; Tax Rates <span class="hint">({{ $rates->flatten()->count() }} agencies)</span></h2>
            </div>
            <div class="grow-list">
                @forelse ($rates as $agency => $agencyRates)
                    @php $rate = $agencyRates->first(); @endphp
                    <li>
                        <div style="min-width:0">
                            <div style="font-weight:600">
                                {{ $agency === 'BIR' ? 'BIR Withholding' : $agency }}
                                <span class="badge badge-green">Effective {{ $rate->effective_from?->format('M j, Y') }}</span>
                            </div>
                            <div class="num" style="font-size:11.5px; color:var(--ink-400)">
                                {{ $rate->name }}
                            </div>
                            <div class="num" style="font-size:11px; color:var(--ink-500); margin-top:2px">
                                @php
                                    $cfg = $rate->config ?? [];
                                    $summary = match ($agency) {
                                        'GSIS' => 'EE ' . ($cfg['employee_rate'] ?? 9) . '% · ER ' . ($cfg['employer_rate'] ?? 12) . '%',
                                        'PHILHEALTH' => ($cfg['rate'] ?? 5) . '% premium (floor ₱' . number_format($cfg['min_premium'] ?? 500) . ' · cap ₱' . number_format($cfg['max_premium'] ?? 5000) . ')',
                                        'PAGIBIG' => 'EE ' . ($cfg['std_employee'] ?? 2) . '% capped ₱' . number_format($cfg['cap_employee'] ?? 200),
                                        'BIR' => 'TRAIN brackets · annualized, PERA-exempt',
                                        default => '—',
                                    };
                                @endphp
                                {{ $summary }}
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="text-muted">No contribution rates seeded. Run <code>php artisan db:seed --class=ContributionRateSeeder</code>.</li>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Periods --}}
    <div class="card">
        <div class="card-header">
            <h2>Payroll Periods <span class="hint">({{ $periods->total() }})</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Payroll Date</th>
                        <th>Status</th>
                        <th style="text-align:right">Net Pay</th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($periods as $period)
                        <tr>
                            <td>
                                <strong>{{ $period->name }}</strong>
                                <div class="num" style="font-size:11.5px; color:var(--ink-400)">{{ $period->items_count }} employee(s) · {{ $period->period_from->format('M j') }} – {{ $period->period_to->format('M j, Y') }}</div>
                            </td>
                            <td>{{ $period->payroll_date->format('M j, Y') }}</td>
                            <td>
                                <span class="badge {{ $period->status === 'finalized' ? 'badge-indigo' : ($period->status === 'paid' ? 'badge-green' : ($period->status === 'voided' ? 'badge-gray' : 'badge-amber')) }}">
                                    {{ ucfirst($period->status) }}
                                </span>
                            </td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $period->items_sum_net_amount, 2) }}</td>
                            <td class="actions">
                                <a href="{{ route('payroll.show', $period) }}" class="btn btn-outline btn-sm">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">No payroll periods yet. Create your first period to start a run.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-pad">
            {{ $periods->links() }}
        </div>
    </div>
@endsection
