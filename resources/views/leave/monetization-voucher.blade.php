<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VL Monetization Voucher — {{ $monetization->reference_no }}</title>
    <style>
        /* dompdf-safe voucher: tables only, no flexbox/transforms. */
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Serif", "Times New Roman", serif; font-size: 12px; color: #000; margin: 0 auto; max-width: 8in; padding: 30px 40px; }

        

        .doc-title { text-align: center; font-size: 18px; font-weight: 700; margin: 0 0 4px; }
        .doc-sub { text-align: center; font-size: 11px; margin-bottom: 18px; }
        .ref-date { text-align: right; font-size: 10.5px; margin-bottom: 16px; }

        table.fields { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.fields td { border: 1px solid #000; padding: 6px 8px; font-size: 11.5px; vertical-align: top; }
        table.fields td.lbl { background: #f2f2f2; font-weight: 700; width: 150px; }

        table.compute { width: 100%; border-collapse: collapse; margin: 10px 0 16px; }
        table.compute th, table.compute td { border: 1px solid #000; padding: 6px 8px; font-size: 11.5px; }
        table.compute th { background: #f2f2f2; text-align: center; font-size: 10.5px; }
        table.compute td.r { text-align: right; }
        table.compute tr.total td { font-weight: 700; background: #f7f7f7; }

        .note { font-size: 10px; font-style: italic; margin: 0 0 14px; }

        .signatures { margin-top: 30px; }
        .signatures table { width: 100%; border-collapse: collapse; }
        .signatures td { width: 50%; padding: 0 10px; vertical-align: top; }
        .sig-label { font-size: 11px; margin-bottom: 30px; }
        .sig-name { border-top: 1px solid #000; padding-top: 4px; font-weight: 700; font-size: 12px; }
        .sig-title { font-size: 10px; margin-top: 1px; }
    </style>
</head>
<body>
    @include('partials.letterhead', ['mb' => 20, 'subtitle' => 'Department of Information and Communications Technology · Regional Office 2'])

    <div class="doc-title">VACATION LEAVE MONETIZATION VOUCHER</div>
    <div class="doc-sub">CSC Omnibus Rules on Leave (MC 41, s. 1998, as amended)</div>

    <div class="ref-date">Ref. No: {{ $monetization->reference_no }}<br>{{ $monetization->processed_at->format('F d, Y') }}</div>

    <table class="fields">
        <tr>
            <td class="lbl">EMPLOYEE</td>
            <td><strong>{{ $monetization->employee->full_name }}</strong></td>
        </tr>
        <tr>
            <td class="lbl">POSITION</td>
            <td>{{ $monetization->employee->position?->title ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">EMPLOYEE NO.</td>
            <td>{{ $monetization->employee->employee_number }}</td>
        </tr>
        <tr>
            <td class="lbl">YEAR</td>
            <td>{{ $monetization->year }}</td>
        </tr>
    </table>

    <table class="compute">
        <thead>
            <tr>
                <th style="width:60%">Item</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Monthly basic salary</td>
                <td class="r">₱ {{ number_format($monetization->employee->monthly_salary, 2) }}</td>
            </tr>
            <tr>
                <td>Rate per day (monthly salary ÷ 22)</td>
                <td class="r">₱ {{ number_format($monetization->per_day_rate, 2) }}</td>
            </tr>
            <tr>
                <td>Vacation leave days monetized</td>
                <td class="r">{{ number_format($monetization->days, 2) }} day(s)</td>
            </tr>
            <tr class="total">
                <td>Gross amount payable</td>
                <td class="r">₱ {{ number_format($monetization->gross_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($monetization->remarks)
        <p class="note">Remarks: {{ $monetization->remarks }}</p>
    @endif

    <p class="note">
        Rules applied: at least 10 VL days accumulated · at least 5 VL days retained after monetization
        · not more than 30 VL days monetized in {{ $monetization->year }}.
        The corresponding VL credit was debited from the employee's leave ledger on {{ $monetization->processed_at->format('F d, Y') }}.
    </p>

    <div class="signatures">
        <table>
            <tr>
                <td>
                    <div class="sig-label">Prepared by:</div>
                    <div class="sig-name">{{ $preparer['name'] }}</div>
                    <div class="sig-title">{{ $preparer['title'] }}</div>
                </td>
                <td>
                    <div class="sig-label">Certified correct for payment:</div>
                    <div class="sig-name">{{ $certifier['name'] }}</div>
                    <div class="sig-title">{{ $certifier['title'] }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
