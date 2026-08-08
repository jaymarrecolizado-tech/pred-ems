@extends('layouts.app')

@section('title', 'My Payslips')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['My Payslips']]])
@endsection

@section('content')
    <div class="card card-pad" style="margin-bottom:18px">
        <div class="overline" style="margin-bottom:6px">Payslip self-service</div>
        <div class="hint">Your finalized payslips, generated from the payroll runs of the Human Resource Information System. Open one to view or download the PDF.</div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>My Payslips <span class="hint">({{ $payslips->total() }})</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Reference No.</th>
                        <th style="text-align:right">Gross</th>
                        <th style="text-align:right">Deductions</th>
                        <th style="text-align:right">Net Pay</th>
                        <th>Generated</th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payslips as $payslip)
                        <tr>
                            <td><strong>{{ $payslip->item->period->name }}</strong></td>
                            <td class="num">{{ $payslip->reference_no }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $payslip->item->gross_amount, 2) }}</td>
                            <td style="text-align:right" class="num">₱{{ number_format((float) $payslip->item->total_deductions, 2) }}</td>
                            <td style="text-align:right" class="num" style="font-weight:700">₱{{ number_format((float) $payslip->item->net_amount, 2) }}</td>
                            <td>{{ $payslip->generated_at?->format('M j, Y') }}</td>
                            <td class="actions">
                                <a href="{{ route('payroll.payslip', $payslip) }}" class="btn btn-outline btn-sm" target="_blank">View PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No payslips yet — they appear here once a payroll period covering you has been finalized.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-pad">
            {{ $payslips->links() }}
        </div>
    </div>
@endsection
