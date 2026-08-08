<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip — {{ $payslip->item->employee->full_name }}</title>
    <style>
        /* Payslip — print / dompdf compatible (no flexbox, tables only). */
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Serif", "Times New Roman", serif;
            font-size: 11px;
            color: #000;
            margin: 0 auto;
            max-width: 8.5in;
            padding: 18px 22px;
        }
        .border { border: 2px solid #000; padding: 14px 16px 12px; }

        /* Letterhead */
        table.letterhead { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .letterhead td { vertical-align: middle; text-align: center; }
        .letterhead td.lh-side { width: 84px; }
        .letterhead td.lh-seal { text-align: left; }
        .letterhead td.lh-logo { text-align: right; }
        .seal {
            width: 64px; height: 64px; margin: 0 auto;
            border: 2px solid #1b2a4a; border-radius: 50%;
            color: #1b2a4a; font-weight: 700; font-size: 13px;
            text-align: center; line-height: 1.15;
            padding-top: 13px;
        }
        .letterhead h1 { margin: 0; font-size: 15px; letter-spacing: .4px; color: #1b2a4a; text-transform: uppercase; }
        .letterhead .sub { font-size: 12px; color: #1b2a4a; margin-top: 2px; }
        .logo { text-align: center; font-size: 9px; font-weight: 700; color: #1b2a4a; }

        .doc-title { text-align: center; font-size: 18px; font-weight: 700; margin: 8px 0 2px; }
        .doc-sub { text-align: center; font-size: 10.5px; margin-bottom: 10px; }
        .ref-no { text-align: right; font-size: 9px; margin-bottom: 4px; }

        /* Employee info */
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.info td { padding: 2px 0; vertical-align: top; }
        table.info td.lbl { width: 120px; font-weight: 700; }

        /* Money tables */
        table.money { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.money th, table.money td { border: 1px solid #000; padding: 4px 6px; }
        table.money th { background: #f2f2f2; font-size: 9px; text-align: center; }
        table.money td { font-size: 10px; }
        table.money td.r { text-align: right; }
        table.money td.total { font-weight: 700; background: #f2f2f2; }

        table.money td.net {
            font-size: 12px; font-weight: 700;
        }

        .net-pay { text-align: center; font-size: 13px; font-weight: 700; margin: 4px 0 10px; }

        .trace-title { font-size: 9.5px; font-weight: 700; margin: 10px 0 4px; }
        table.trace { width: 100%; border-collapse: collapse; }
        table.trace th, table.trace td { border: 1px solid #000; padding: 3px 5px; font-size: 8.5px; }
        table.trace th { background: #f2f2f2; text-align: center; }

        .footnote { font-size: 8px; margin-top: 10px; text-align: justify; }
        .sig-name { border-top: 1px solid #000; padding-top: 3px; font-weight: 700; font-size: 10.5px; }
        .sig-title { font-size: 9px; margin-top: 1px; }
    </style>
</head>
<body>
<div class="border">
    @php
        $item = $payslip->item;
        $employee = $item->employee;
        $period = $item->period;
        $trace = $item->computation_json ?? [];
    @endphp

    {{-- Letterhead --}}
    <table class="letterhead">
        <tr>
            <td class="lh-side lh-seal"><div class="seal">DICT<br>RO2</div></td>
            <td>
                <h1>Republic of the Philippines</h1>
                <div class="sub">Department of Information and Communications Technology</div>
            </td>
            <td class="lh-side lh-logo"><div class="logo">BAGONG<br>PILIPINAS</div></td>
        </tr>
    </table>

    <div class="doc-title">PAYSLIP</div>
    <div class="doc-sub">Payroll Period: {{ $period->period_from->format('M j, Y') }} – {{ $period->period_to->format('M j, Y') }} · Payroll Date: {{ $period->payroll_date->format('M j, Y') }}</div>
    <div class="ref-no">Ref. No: {{ $payslip->reference_no }}</div>

    {{-- Employee info --}}
    <table class="info">
        <tr>
            <td class="lbl">Employee</td>
            <td>{{ $employee->full_name }} ({{ $employee->employee_number }})</td>
            <td class="lbl">Position</td>
            <td>{{ $employee->position?->title ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">GSIS No.</td>
            <td>{{ $employee->gsis_no ?? '—' }}</td>
            <td class="lbl">PhilHealth No.</td>
            <td>{{ $employee->philhealth_no ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">PAG-IBIG No.</td>
            <td>{{ $employee->pagibig_no ?? '—' }}</td>
            <td class="lbl">TIN</td>
            <td>{{ $employee->tin_no ?? '—' }}</td>
        </tr>
    </table>

    {{-- Income --}}
    <table class="money">
        <tr><th colspan="2">EARNINGS</th></tr>
        <tr><td>Basic Salary</td><td class="r">{{ number_format((float) $item->basic_salary, 2) }}</td></tr>
        <tr><td>PERA</td><td class="r">{{ number_format((float) $item->pera, 2) }}</td></tr>
        @if ((float) $item->honoraria > 0)<tr><td>Honoraria</td><td class="r">{{ number_format((float) $item->honoraria, 2) }}</td></tr>@endif
        @if ((float) $item->overtime_pay > 0)<tr><td>Overtime Pay</td><td class="r">{{ number_format((float) $item->overtime_pay, 2) }}</td></tr>@endif
        @if ((float) $item->other_income > 0)<tr><td>Other Income</td><td class="r">{{ number_format((float) $item->other_income, 2) }}</td></tr>@endif
        <tr><td class="total">GROSS AMOUNT</td><td class="r total">{{ number_format((float) $item->gross_amount, 2) }}</td></tr>
    </table>

    {{-- Deductions --}}
    <table class="money">
        <tr><th colspan="2">DEDUCTIONS</th></tr>
        <tr><td>GSIS (employee share)</td><td class="r">{{ number_format((float) $item->gsis_employee_share, 2) }}</td></tr>
        <tr><td>PhilHealth (employee share)</td><td class="r">{{ number_format((float) $item->philhealth_employee_share, 2) }}</td></tr>
        <tr><td>PAG-IBIG (employee share)</td><td class="r">{{ number_format((float) $item->pagibig_employee_share, 2) }}</td></tr>
        <tr><td>Withholding Tax</td><td class="r">{{ number_format((float) $item->withholding_tax, 2) }}</td></tr>
        @if ((float) $item->lwop_deduction > 0)<tr><td>Leave without Pay</td><td class="r">{{ number_format((float) $item->lwop_deduction, 2) }}</td></tr>@endif
        @if ((float) $item->other_deductions > 0)<tr><td>Other Deductions</td><td class="r">{{ number_format((float) $item->other_deductions, 2) }}</td></tr>@endif
        <tr><td class="total">TOTAL DEDUCTIONS</td><td class="r total">{{ number_format((float) $item->total_deductions, 2) }}</td></tr>
    </table>

    <div class="net-pay">NET PAY: ₱{{ number_format((float) $item->net_amount, 2) }}</div>

    @if (! empty($trace['lines']))
        <div class="trace-title">Computation Trace (audit)</div>
        <table class="trace">
            <thead>
                <tr><th style="width:34%">Item</th><th style="width:16%">Base</th><th style="width:12%">Rate</th><th style="width:16%">Amount</th><th>Note</th></tr>
            </thead>
            <tbody>
                @foreach ($trace['lines'] as $line)
                    <tr>
                        <td>{{ $line['label'] }}</td>
                        <td class="r">{{ isset($line['base']) ? number_format((float) $line['base'], 2) : '—' }}</td>
                        <td class="r">{{ $line['rate'] ?? '—' }}</td>
                        <td class="r">{{ number_format((float) $line['amount'], 2) }}</td>
                        <td>{{ $line['note'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table style="width:100%; border-collapse:collapse; margin-top:22px">
        <tr>
            <td style="width:50%; padding:0 10px">
                <div style="font-size:9.5px; margin-bottom:26px">Prepared by:</div>
                <div class="sig-name">{{ $payslip->generator?->name ?? '—' }}</div>
                <div class="sig-title">Payroll Officer</div>
            </td>
            <td style="width:50%; padding:0 10px">
                <div style="font-size:9.5px; margin-bottom:26px">Received by:</div>
                <div class="sig-name">{{ $employee->full_name }}</div>
                <div class="sig-title">{{ $employee->position?->title ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <div class="footnote">
        Generated by the DICT Regional Office 2 HRIS on {{ $payslip->generated_at?->format('F j, Y g:i A') }}. This payslip is a system-generated document; amounts follow the contribution and tax rules effective for the payroll period (GSIS RA 8291, PhilHealth UHC Act, PAG-IBIG Circular 460, BIR TRAIN Law).
    </div>
</div>
</body>
</html>
