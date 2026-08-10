@php
    $emp = $dtr['employee'];
    $days = $dtr['days'];
    $totals = $dtr['totals'];
    $hours = $dtr['officeHours'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Time Record — {{ $emp->full_name }} ({{ $dtr['monthLabel'] }})</title>
    <style>
        /* CSC Form 48 — Daily Time Record. Table-only layout for dompdf. */
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Serif", "Times New Roman", serif;
            font-size: 9px;
            color: #000;
            margin: 0 auto;
            max-width: 8.5in;
            padding: 16px 22px;
        }

        /* ---------- Letterhead ---------- */
        

        .doc-title { text-align: center; font-size: 17px; font-weight: 700; margin: 8px 0 2px; letter-spacing: 1px; }
        .ref-no { text-align: right; font-size: 8px; margin-bottom: 4px; }

        /* ---------- Header info grid ---------- */
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.info td { border: 1px solid #000; padding: 3px 6px; font-size: 9.5px; }
        table.info .lbl { width: 118px; font-weight: 700; background: #f2f2f2; }

        /* ---------- DTR grid ---------- */
        table.dtr { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.dtr th, table.dtr td { border: 1px solid #000; padding: 2.5px 3px; text-align: center; font-size: 8.5px; }
        table.dtr thead th { background: #f2f2f2; font-weight: 700; }
        table.dtr .weekend { background: #f9f9f9; }
        table.dtr .rest-day { background: #f6f8fb; }
        table.dtr .holiday { background: #fbf4f2; }
        table.dtr .totals-row td { font-weight: 700; background: #f2f2f2; }
        table.dtr .num { font-variant-numeric: tabular-nums; }
        .col-day { width: 7%; }
        .col-punch { width: 12.5%; }
        .col-hours { width: 10%; }
        .col-remark { width: 16%; }

        .day-row td { height: 14px; }
        .empty-punch { color: #999; }

        /* ---------- Footer ---------- */
        .summary { font-size: 8.5px; margin-top: 8px; line-height: 1.5; }
        .signatures { margin-top: 26px; }
        .signatures table { width: 100%; border-collapse: collapse; }
        .signatures td { width: 33%; padding: 0 8px; vertical-align: top; }
        .sig-label { font-size: 9px; margin-bottom: 30px; }
        .sig-name { border-top: 1px solid #000; padding-top: 3px; font-weight: 700; font-size: 10px; }
        .sig-title { font-size: 8.5px; margin-top: 1px; }
    </style>
</head>
<body>
    {{-- Letterhead --}}
    @include('partials.letterhead', ['compact' => true])

    <div class="doc-title">DAILY TIME RECORD</div>
    @if (isset($referenceNo) && $referenceNo)
        <div class="ref-no">Ref. No: {{ $referenceNo }}</div>
    @endif

    {{-- Header info --}}
    <table class="info">
        <tr>
            <td class="lbl">Name</td>
            <td colspan="3"><strong>{{ $emp->full_name }}</strong> {{ $emp->middle_name ? '(M.I. ' . mb_substr($emp->middle_name, 0, 1) . '.)' : '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Office / Division</td>
            <td colspan="3">{{ $emp->division?->name ?? 'Regional Office No. 2' }}</td>
        </tr>
        <tr>
            <td class="lbl">Position</td>
            <td style="width:34%">{{ $emp->position?->title ?? '—' }}</td>
            <td class="lbl" style="width:90px">For the Month of</td>
            <td style="width:30%"><strong>{{ $dtr['monthLabel'] }}</strong></td>
        </tr>
    </table>

    {{-- DTR grid --}}
    <table class="dtr">
        <thead>
            <tr>
                <th class="col-day" rowspan="2">Day</th>
                <th class="col-punch">AM In</th>
                <th class="col-punch">AM Out</th>
                <th class="col-punch">PM In</th>
                <th class="col-punch">PM Out</th>
                <th class="col-hours" rowspan="2">No. of Hours</th>
                <th class="col-remark" rowspan="2">Remarks</th>
            </tr>
            <tr>
                <th>a.m.</th>
                <th>a.m.</th>
                <th>p.m.</th>
                <th>p.m.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($days as $row)
                @php
                    $rowClass = $row['is_holiday'] ? 'holiday' : ($row['is_rest_day'] ? 'rest-day' : '');
                @endphp
                <tr class="day-row {{ $rowClass }}">
                    <td class="num">{{ $row['day'] }}</td>
                    <td class="num">{{ $row['am_in']?->format('h:i A') ?? '·' }}</td>
                    <td class="num">{{ $row['am_out']?->format('h:i A') ?? '·' }}</td>
                    <td class="num">{{ $row['pm_in']?->format('h:i A') ?? '·' }}</td>
                    <td class="num">{{ $row['pm_out']?->format('h:i A') ?? '·' }}</td>
                    <td class="num">{{ $row['hours'] > 0 ? number_format($row['hours'], 2) : '' }}</td>
                    <td style="text-align:left">
                        @if ($row['is_holiday'])
                            <strong>Holiday: {{ $row['holiday_name'] }}</strong>
                            @if ($row['hours'] > 0)· OT (CTO)@endif
                        @elseif ($row['is_rest_day'])
                            @if ($row['hours'] > 0)OT (CTO)@else Rest day @endif
                        @else
                            @if ($row['late'] > 0) Late {{ $row['late'] }}m;@endif
                            @if ($row['undertime'] > 0) Under {{ $row['undertime'] }}m;@endif
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr class="totals-row">
                <td>TOTAL</td>
                <td colspan="4" style="text-align:right">Present: {{ $totals['present'] }} day(s) · Late: {{ $totals['late'] }}m · Under: {{ $totals['undertime'] }}m</td>
                <td class="num">{{ number_format($totals['hours'], 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- Summary / schedule --}}
    <div class="summary">
        Schedule: {{ $dtr['scheduleLabel'] }}.
        This record is generated from the geofenced attendance system. Rest days, weekends, and holidays with rendered hours are overtime (CTO-eligible) timelogs; alterations require an approved HR correction request.
    </div>

    {{-- Signatures --}}
    <div class="signatures">
        <table>
            <tr>
                <td>
                    <div class="sig-label">Signature of Employee</div>
                    <div class="sig-name">{{ $emp->full_name }}</div>
                    <div class="sig-title">{{ $emp->position?->title ?? '' }}</div>
                </td>
                <td>
                    <div class="sig-label">Certified Correct</div>
                    <div class="sig-name">Head of Office</div>
                    <div class="sig-title">Regional Director / CAO</div>
                </td>
                <td>
                    <div class="sig-label">Verified by</div>
                    <div class="sig-name">Human Resource Office</div>
                    <div class="sig-title">HRMO</div>
                </td>
            </tr>
        </table>
    </div>

    @include('partials.doc-qr')
</body>
</html>
