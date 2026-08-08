@extends('layouts.app')

@section('title', 'Payroll — ' . $period->name)

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Payroll', route('payroll.index')], [$period->name]]])
@endsection

@section('content')
    {{-- Period header --}}
    <div class="card card-pad" style="margin-bottom:18px">
        <div class="overline" style="margin-bottom:6px">Payroll period</div>
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap">
            <h2 style="margin:0">{{ $period->name }}</h2>
            <span class="badge {{ $period->status === 'finalized' ? 'badge-indigo' : ($period->status === 'paid' ? 'badge-green' : ($period->status === 'voided' ? 'badge-gray' : 'badge-amber')) }}">
                {{ ucfirst($period->status) }}
            </span>
            <span class="hint">
                {{ $period->period_from->format('M j, Y') }} → {{ $period->period_to->format('M j, Y') }} · paid {{ $period->payroll_date->format('M j, Y') }}
            </span>
            @if ($period->finalized_at)
                <span class="hint">Finalized {{ $period->finalized_at->format('M j, Y g:i A') }} by {{ $period->finalizedBy?->name ?? '—' }}</span>
            @endif
            @if ($period->remarks)
                <span class="hint">{{ $period->remarks }}</span>
            @endif

            <div style="margin-left:auto; display:flex; gap:8px; flex-wrap:wrap">
                @if ($period->isDraft())
                    <form method="POST" action="{{ route('payroll.generate', $period) }}" class="inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ $period->items->isEmpty() ? 'Compute Payroll' : 'Recompute Payroll' }}</button>
                    </form>
                    @if (! $period->items->isEmpty())
                        <form method="POST" action="{{ route('payroll.finalize', $period) }}" class="inline" onsubmit="return confirm('Finalizing locks this period and issues payslips + remittance summaries. Continue?')">
                            @csrf
                            <button type="submit" class="btn" style="background:#7c3aed; color:#fff">Finalize &amp; Issue Payslips</button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('payroll.remittances') }}" class="btn btn-outline">Remittances</a>
                @endif
            </div>
        </div>
    </div>

    {{-- Totals --}}
    <div class="stat-row" style="margin-bottom:20px">
        <div class="stat-tile">
            <div>
                <div class="stat-tile-label">Employees</div>
                <div class="stat-tile-value">{{ $period->items->count() }}</div>
            </div>
        </div>
        <div class="stat-tile">
            <div>
                <div class="stat-tile-label">Gross Pay</div>
                <div class="stat-tile-value">₱{{ number_format($totals->gross, 2) }}</div>
            </div>
        </div>
        <div class="stat-tile">
            <div>
                <div class="stat-tile-label">Total Deductions</div>
                <div class="stat-tile-value">₱{{ number_format($totals->deductions, 2) }}</div>
            </div>
        </div>
        <div class="stat-tile">
            <div>
                <div class="stat-tile-label">Net Pay</div>
                <div class="stat-tile-value" style="color:var(--brand-600)">₱{{ number_format($totals->net, 2) }}</div>
            </div>
        </div>
        <div class="stat-tile">
            <div>
                <div class="stat-tile-label">GSIS · PhilHealth · PAG-IBIG · Tax</div>
                <div class="stat-tile-value" style="font-size:15px">₱{{ number_format($totals->gsis, 2) }} · ₱{{ number_format($totals->philhealth, 2) }} · ₱{{ number_format($totals->pagibig, 2) }} · ₱{{ number_format($totals->tax, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Items --}}
    <div class="card">
        <div class="card-header">
            <h2>Payroll Items <span class="hint">({{ $period->items->count() }})</span></h2>
            @if ($period->isDraft() && $period->items->isNotEmpty())
                <span class="hint" style="font-size:12px">Draft — recompute before finalizing if anything changed.</span>
            @endif
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th style="text-align:right">Basic</th>
                        <th style="text-align:right">PERA</th>
                        <th style="text-align:right">Gross</th>
                        <th style="text-align:right">GSIS</th>
                        <th style="text-align:right">PhilHealth</th>
                        <th style="text-align:right">PAG-IBIG</th>
                        <th style="text-align:right">Tax</th>
                        <th style="text-align:right">Deductions</th>
                        <th style="text-align:right">Net</th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($period->items as $item)
                        @php $e = $item->employee; @endphp
                        <tr>
                            <td>
                                <strong>{{ $e->full_name }}</strong>
                                <div class="num" style="font-size:11.5px; color:var(--ink-400)">{{ $e->position?->title ?? '—' }}</div>
                            </td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $item->basic_salary, 2) }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $item->pera, 2) }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $item->gross_amount, 2) }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $item->gsis_employee_share, 2) }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $item->philhealth_employee_share, 2) }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $item->pagibig_employee_share, 2) }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $item->withholding_tax, 2) }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $item->total_deductions, 2) }}</td>
                            <td style="text-align:right" class="num" style="font-weight:700">₱{{ number_format((float) $item->net_amount, 2) }}</td>
                            <td class="actions">
                                @if ($item->payslip)
                                    <a href="{{ route('payroll.payslip', $item->payslip) }}" class="btn btn-outline btn-sm" target="_blank">Payslip</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-muted">No items yet. Click <strong>Compute Payroll</strong> to generate one row per eligible employee.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($period->items->isNotEmpty() && $period->isLocked())
        <div class="card card-pad" style="margin-top:18px">
            <div class="overline" style="margin-bottom:8px">Audit trail — computation retained</div>
            <div class="hint">Every item keeps its full computation trace (rates used, bases, brackets) in <code>computation_json</code>. Open any payslip to see the per-employee breakdown. Corrections to a finalized period are handled via a reversal/adjustment period.</div>
        </div>
    @endif
@endsection
