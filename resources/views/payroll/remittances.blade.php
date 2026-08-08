@extends('layouts.app')

@section('title', 'Remittances')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Payroll', route('payroll.index')], ['Remittances']]])
@endsection

@section('content')
    <div class="card card-pad" style="margin-bottom:18px">
        <div class="overline" style="margin-bottom:6px">Statutory contributions</div>
        <div class="hint">Summaries are generated automatically when a payroll period is finalized. Mark each agency batch as <strong>remitted</strong> once the payment/report has been submitted, and record the reference number.</div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Remittance Register <span class="hint">({{ $remittances->total() }})</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Agency</th>
                        <th>Period</th>
                        <th style="text-align:right">Employee Share</th>
                        <th style="text-align:right">Employer Share</th>
                        <th style="text-align:right">Grand Total</th>
                        <th>Status</th>
                        <th>Reference No.</th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($remittances as $remittance)
                        <tr>
                            <td><strong>{{ $remittance->agency }}</strong></td>
                            <td>{{ $remittance->period_from->format('M j, Y') }} → {{ $remittance->period_to->format('M j, Y') }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $remittance->employee_share_total, 2) }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $remittance->employer_share_total, 2) }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $remittance->grand_total, 2) }}</td>
                            <td>
                                <span class="badge {{ $remittance->status === 'remitted' ? 'badge-green' : ($remittance->status === 'verified' ? 'badge-indigo' : 'badge-amber') }}">
                                    {{ ucfirst($remittance->status) }}
                                </span>
                            </td>
                            <td class="num">{{ $remittance->reference_no ?? '—' }}</td>
                            <td class="actions">
                                @if ($remittance->isPending())
                                    <details style="position:relative">
                                        <summary class="btn btn-outline btn-sm" style="cursor:pointer">Mark Remitted</summary>
                                        <form method="POST" action="{{ route('payroll.remittances.remit', $remittance) }}" class="card" style="position:absolute; right:0; top:calc(100% + 6px); z-index:20; padding:12px; width:260px; box-shadow:0 10px 30px rgba(15,23,42,.15)">
                                            @csrf
                                            <div class="field" style="margin-bottom:8px">
                                                <label for="ref-{{ $remittance->id }}">Reference / OR No.</label>
                                                <input type="text" id="ref-{{ $remittance->id }}" name="reference_no" placeholder="e.g. GSIS batch no. 2026-08">
                                            </div>
                                            <div class="field" style="margin-bottom:8px">
                                                <label for="rem-{{ $remittance->id }}">Remarks</label>
                                                <input type="text" id="rem-{{ $remittance->id }}" name="remarks" placeholder="Optional">
                                            </div>
                                            <button type="submit" class="btn btn-primary" style="width:100%">Confirm Remittance</button>
                                        </form>
                                    </details>
                                @else
                                    <span class="hint" style="font-size:11.5px">{{ $remittance->remitted_at?->format('M j, Y g:i A') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">No remittance summaries yet — they are generated when a payroll period is finalized.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-pad">
            {{ $remittances->links() }}
        </div>
    </div>
@endsection
