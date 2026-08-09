@php
    $app = $application;
    $emp = $app->employee;
    $isChecked = fn (string $code) => $app->leaveType->code === $code;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSC Form No. 6 — {{ $emp->full_name }}</title>
    <style>
        /* CSC Form No. 6 (Application for Leave) — dompdf-safe tables only. */
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #000; margin: 0 auto; max-width: 8.2in; padding: 22px 28px; }
        table { width: 100%; border-collapse: collapse; }
        td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        td.nb { border: none; }

        .hdr { text-align: center; font-size: 10.5px; }
        .hdr b { font-size: 11.5px; }
        .lbl { background: #ececec; font-weight: 700; width: 128px; }
        .sub-lbl { font-size: 8px; color: #333; font-weight: 400; }
        .cb { display: inline-block; width: 9px; height: 9px; border: 1px solid #000; font-size: 9px; line-height: 9px; text-align: center; margin: 0 3px 0 1px; vertical-align: -1px; }
        .type-row { display: block; margin: 2px 0; }
        .detail-line { margin: 4px 0; }
        .sig { text-align: center; padding-top: 34px; font-size: 11px; }
        .sig .line { border-top: 1px solid #000; margin-top: 30px; padding-top: 3px; font-weight: 700; }
        .sec { text-align: center; font-weight: 700; background: #ececec; font-size: 10.5px; }
    </style>
</head>
<body>
    {{-- Form heading --}}
    <table>
        <tr>
            <td class="nb hdr" style="width:52%">
                Republic of the Philippines<br>
                <b>DEPARTMENT OF INFORMATION AND COMMUNICATIONS TECHNOLOGY</b><br>
                <b>Regional Office 2</b>
            </td>
            <td class="nb hdr">
                CIVIL SERVICE COMMISSION<br>
                <b>APPLICATION FOR LEAVE</b><br>
                Form No. 6, Revised 2020
            </td>
        </tr>
    </table>

    {{-- Office / date / name / position / salary --}}
    <table style="margin-top:10px">
        <tr>
            <td class="lbl">OFFICE/AGENCY</td>
            <td>DICT Regional Office 2</td>
            <td class="lbl" style="width:112px">DATE OF FILING</td>
            <td style="width:120px">{{ $app->created_at->format('m/d/Y') }}</td>
        </tr>
    </table>
    <table style="margin-top:6px">
        <tr>
            <td class="lbl">1. NAME</td>
            <td style="width:33%"><span class="sub-lbl">(Surname)</span><br>{{ $emp->last_name }}</td>
            <td style="width:33%"><span class="sub-lbl">(First Name)</span><br>{{ $emp->first_name }}</td>
            <td><span class="sub-lbl">(Middle Name)</span><br>{{ $emp->middle_name ?: '—' }}</td>
        </tr>
    </table>
    <table style="margin-top:6px">
        <tr>
            <td class="lbl">2. POSITION</td>
            <td>{{ $emp->position?->title ?? '—' }}</td>
            <td class="lbl" style="width:100px">3. SALARY</td>
            <td style="width:150px">{{ $emp->monthly_salary ? '₱' . number_format($emp->monthly_salary, 2) : '—' }}</td>
        </tr>
    </table>
    <table style="margin-top:6px">
        <tr>
            <td class="lbl">4. OFFICE/DEPARTMENT</td>
            <td>{{ $emp->division?->name ?? '—' }}</td>
        </tr>
    </table>

    {{-- Details of application --}}
    <table style="margin-top:10px">
        <tr><td class="sec">DETAILS OF APPLICATION</td></tr>
        <tr>
            <td>
                <b>5. TYPE OF LEAVE:</b>
                <span class="type-row"><span class="cb">{!! $isChecked('VL') ? 'X' : '&nbsp;' !!}</span> Vacation</span>
                <span class="type-row"><span class="cb">{!! $isChecked('SL') ? 'X' : '&nbsp;' !!}</span> Sick</span>
                <span class="type-row"><span class="cb">{!! $isChecked('MATERNITY') ? 'X' : '&nbsp;' !!}</span> Maternity</span>
                <span class="type-row"><span class="cb">{!! $isChecked('PATERNITY') ? 'X' : '&nbsp;' !!}</span> Paternity</span>
                <span class="type-row"><span class="cb">{!! $isChecked('SLP') ? 'X' : '&nbsp;' !!}</span> Special Leave Privileges</span>
                <span class="type-row"><span class="cb">{!! $isChecked('SOLO_PARENT') ? 'X' : '&nbsp;' !!}</span> Solo Parent</span>
                <span class="type-row"><span class="cb">{!! $isChecked('VAWC') ? 'X' : '&nbsp;' !!}</span> VAWC</span>
                <span class="type-row"><span class="cb">{!! $isChecked('STUDY') ? 'X' : '&nbsp;' !!}</span> Study</span>
                <span class="type-row"><span class="cb">{!! $isChecked('SLW') ? 'X' : '&nbsp;' !!}</span> Special Leave for Women</span>
                <span class="type-row"><span class="cb">{!! in_array($app->leaveType->code, ['LWOP', 'OTHERS'], true) ? 'X' : '&nbsp;' !!}</span> Others: <u>{{ in_array($app->leaveType->code, ['LWOP', 'OTHERS'], true) ? $app->leaveType->name : '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' }}</u></span>
            </td>
        </tr>
        <tr>
            <td>
                <b>6. DETAILS OF LEAVE:</b>
                <div class="detail-line">Inclusive dates: <u>&nbsp;{{ $app->date_from->format('F d, Y') }}&nbsp;</u> to <u>&nbsp;{{ $app->date_to->format('F d, Y') }}&nbsp;</u></div>
                <div class="detail-line">Number of working days: <u>&nbsp;{{ number_format($app->days_applied, 2) }}&nbsp;</u></div>
                <div class="detail-line">Commutation: <span class="cb">{!! $app->commutation_requested ? 'X' : '&nbsp;' !!}</span> Requested &nbsp;&nbsp;&nbsp; <span class="cb">{!! $app->commutation_requested ? '&nbsp;' : 'X' !!}</span> Not requested</div>
            </td>
        </tr>
        <tr>
            <td><b>7. REASON FOR LEAVE:</b><br>{{ $app->reason }}</td>
        </tr>
        <tr>
            <td>
                <div class="sig">
                    <div style="font-size:10px; text-align:left">I certify that my answers to the above questions are true and correct.</div>
                    <div class="line">{{ $emp->full_name }}</div>
                    <div style="font-size:9px">Signature of Applicant</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Office use --}}
    <table style="margin-top:10px">
        <tr><td class="sec">RECOMMENDATION — FOR OFFICE USE ONLY</td></tr>
        <tr>
            <td>
                <div class="detail-line">Recommending approval: <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u></div>
                <div class="detail-line">Approved: <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u></div>
                <div class="detail-line">Leave credits: <span class="cb">&nbsp;</span> With pay &nbsp;&nbsp; <span class="cb">&nbsp;</span> Without pay &nbsp;&nbsp; As of: <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u></div>
            </td>
        </tr>
    </table>

    <table style="margin-top:8px">
        <tr>
            <td class="nb" style="width:50%">Supporting documents (if any):</td>
            <td class="nb">For SLP: certification of urgent/important reasons must be attached.</td>
        </tr>
    </table>
</body>
</html>
