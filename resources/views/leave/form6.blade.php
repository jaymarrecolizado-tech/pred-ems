@php
    $app = $application;
    $emp = $app->employee;
    $code = $app->leaveType->code;

    $cb = fn (bool $on) => '<span class="cb">' . ($on ? 'X' : '&nbsp;') . '</span>';

    // Official Form 6 leave catalogue (checkbox rows + legal bases).
    $rows = [
        ['Vacation Leave', '(Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', ['VL']],
        ['Mandatory/Forced Leave', '(Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', []],
        ['Sick Leave', '(Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', ['SL']],
        ['Maternity Leave', '(R.A. No. 11210 / IRR issued by CSC, DOLE and SSS)', ['MATERNITY']],
        ['Paternity Leave', '(R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended)', ['PATERNITY']],
        ['Special Privilege Leave', '(Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', ['SLP']],
        ['Solo Parent Leave', '(RA No. 8972 / CSC MC No. 8, s. 2004)', ['SOLO_PARENT']],
        ['Study Leave', '(Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', ['STUDY']],
        ['10-Day VAWC Leave', '(RA No. 9262 / CSC MC No. 15, s. 2005)', ['VAWC']],
        ['Rehabilitation Privilege', '(Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', []],
        ['Special Leave Benefits for Women', '(RA No. 9710 / CSC MC No. 25, s. 2010)', ['SLW']],
        ['Special Emergency (Calamity) Leave', '(CSC MC No. 2, s. 2012, as amended)', []],
        ['Adoption Leave', '(R.A. No. 8552)', []],
        ['Others', '', []],
    ];
    $knownCodes = array_merge(...array_column($rows, 2));
    $othersChecked = $code === 'LWOP' || ! in_array($code, $knownCodes, true);
    $isOn = fn (array $codes) => in_array($code, $codes, true);

    $credits = $credits ?? ['vl' => ['earned' => '', 'less' => '', 'balance' => ''], 'sl' => ['earned' => '', 'less' => '', 'balance' => '']];
    $asOf = $asOf ?? now()->format('F j, Y');
    $hrmo = $hrmo ?? ['name' => '', 'title' => 'HRMO II'];
    $adminFinance = $adminFinance ?? ['name' => '', 'title' => 'OIC, Admin. and Finance Division'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSC Form No. 6 — {{ $emp->full_name }}</title>
    <style>
        /* CSC Form No. 6 (Application for Leave), Revised 2020 — dompdf-safe tables only. */
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #000; margin: 0 auto; max-width: 8.2in; padding: 12px 20px; }
        table { width: 100%; border-collapse: collapse; }
        td { border: 1px solid #000; padding: 2px 5px; vertical-align: top; }
        td.nb { border: none; }
        td.pad0 { padding: 0; }
        td.sig-cell { text-align: center; font-weight: 700; padding-top: 22px; font-size: 10.5px; }
        td.sig-title-cell { text-align: center; font-size: 9px; padding-top: 0; }

        /* Header */
        .form-no { font-size: 7.5px; color: #333; text-align: left; }
        .title { text-align: center; font-size: 16px; font-weight: 700; letter-spacing: 1px; }
        .masthead { text-align: right; font-size: 7.5px; color: #333; line-height: 1.4; }

        /* Sections */
        .lbl { background: #ececec; font-weight: 700; }
        .sub { background: #ececec; font-weight: 700; font-size: 9.5px; }
        .sec { text-align: center; font-weight: 700; background: #ececec; font-size: 10px; padding: 4px 6px; }
        .hdr2 { background: #f2f2f2; font-weight: 700; text-align: center; font-size: 8.5px; }
        .small { font-size: 8px; color: #333; }
        .cb { display: inline-block; width: 9px; height: 9px; border: 1px solid #000; font-size: 9px; line-height: 9px; text-align: center; margin: 0 3px 0 1px; vertical-align: -1px; }

        /* 6.A leave types */
        .trow { margin: 0; line-height: 1.32; }
        .tname { font-weight: 700; }
        .basis { font-size: 7px; color: #444; }
        .others-line u { font-size: 8px; }

        /* 6.B details */
        .dhead { font-weight: 700; margin: 4px 0 0; }
        .drow { line-height: 1.4; margin: 0; }
        .drow u { font-weight: 400; }
        .sig-app { margin-top: 8px; text-align: center; font-size: 8px; }
        .sig-app .line { border-top: 1px solid #000; margin-top: 20px; padding-top: 2px; }
        .num { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body>
    {{-- Header --}}
    <table>
        <tr>
            <td class="nb" style="width:30%"><div class="form-no">Civil Service Form No. 6<br>Revised 2020</div></td>
            <td class="nb" style="text-align:center"><div class="title">APPLICATION FOR LEAVE</div></td>
            <td class="nb" style="width:30%">
                <div class="masthead">Republic of the Philippines<br>Department of Information and Communications Technology<br>Regional Office 2</div>
            </td>
        </tr>
    </table>

    {{-- 1–2: office & name --}}
    <table style="margin-top:6px">
        <tr>
            <td class="lbl" colspan="4">1. OFFICE/DEPARTMENT - DISTRICT/SCHOOL</td>
            <td class="lbl" colspan="5">2. NAME: &nbsp;<span class="small">(Last)</span> &nbsp;&nbsp;&nbsp; <span class="small">(First)</span> &nbsp;&nbsp;&nbsp; <span class="small">(Middle)</span></td>
        </tr>
        <tr>
            <td colspan="4">DICT Regional Office 2</td>
            <td>{{ $emp->last_name }}</td>
            <td>{{ $emp->first_name }}</td>
            <td>{{ $emp->middle_name ?: '—' }}</td>
            <td class="nb"></td>
            <td class="nb"></td>
        </tr>
    </table>

    {{-- 3–5: filing date, position, salary --}}
    <table style="margin-top:4px">
        <tr>
            <td class="lbl" colspan="2">3. DATE OF FILING</td>
            <td colspan="2">{{ $app->created_at->format('m/d/Y') }}</td>
            <td class="lbl">4. POSITION</td>
            <td>{{ $emp->position?->title ?? '—' }}</td>
            <td class="lbl">5. SALARY</td>
            <td>{{ $emp->monthly_salary ? '₱' . number_format($emp->monthly_salary, 2) : '—' }}</td>
        </tr>
    </table>

    {{-- 6. DETAILS OF APPLICATION --}}
    <table style="margin-top:6px">
        <tr><td class="sec" colspan="2">6. DETAILS OF APPLICATION</td></tr>
        <tr>
            <td class="sub" style="width:57%">6.A &nbsp;TYPE OF LEAVE TO BE AVAILED OF</td>
            <td class="sub">6.B &nbsp;DETAILS OF LEAVE</td>
        </tr>
        <tr>
            <td>
                @foreach ($rows as [$label, $basis, $codes])
                    <div class="trow">
                        {!! $cb($isOn($codes)) !!}
                        <span class="tname">{{ $label }}</span>
                        @if ($basis)
                            <span class="basis"> {{ $basis }}</span>
                        @endif
                        @if ($label === 'Others')
                            <span class="others-line"><u>{{ $othersChecked ? $app->leaveType->name : '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' }}</u></span>
                        @endif
                    </div>
                @endforeach
            </td>
            <td>
                <div class="dhead">In case of Vacation/Special Privilege Leave:</div>
                <div class="drow">{!! $cb(false) !!} Within the Philippines ______________________</div>
                <div class="drow">{!! $cb(false) !!} Abroad (Specify) _____________________________</div>
                <div class="dhead">In case of Sick Leave:</div>
                <div class="drow">{!! $cb(false) !!} In Hospital (Specify Illness) ___________________</div>
                <div class="drow">{!! $cb(false) !!} Out Patient (Specify Illness) ____________________</div>
                <div class="drow">____________________________________________________________________</div>
                <div class="dhead">In case of Special Leave Benefits for Women:</div>
                <div class="drow">{!! $cb(false) !!} (Specify Illness) __________________________________</div>
                <div class="drow">____________________________________________________________________</div>
                <div class="dhead">In case of Study Leave:</div>
                <div class="drow">{!! $cb(false) !!} Completion of Master's Degree</div>
                <div class="drow">{!! $cb(false) !!} BAR/Board Examination Review</div>
                <div class="dhead">Other purpose:</div>
                <div class="drow">{!! $cb(false) !!} Monetization of Leave Credits</div>
                <div class="drow">{!! $cb(false) !!} Terminal Leave</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="drow" style="margin-top:4px"><b>6.C &nbsp;NUMBER OF WORKING DAYS APPLIED FOR:</b></div>
                <div class="drow"><u>&nbsp;&nbsp;{{ $app->days_applied }}&nbsp;&nbsp;</u> day(s)</div>
                <div class="drow" style="margin-top:5px"><b>INCLUSIVE DATES:</b></div>
                <div class="drow"><u>&nbsp;{{ $app->date_from->format('F d, Y') }}&nbsp;</u> to <u>&nbsp;{{ $app->date_to->format('F d, Y') }}&nbsp;</u></div>
            </td>
            <td>
                <div class="drow" style="margin-top:4px"><b>6.D &nbsp;COMMUTATION:</b></div>
                <div class="drow">{!! $cb(! $app->commutation_requested) !!} Not Requested</div>
                <div class="drow">{!! $cb($app->commutation_requested) !!} Requested</div>
                <div class="sig-app"><div class="line">{{ $emp->full_name }}</div>Signature of Applicant</div>
            </td>
        </tr>
    </table>

    {{-- 7. DETAILS OF ACTION ON APPLICATION --}}
    <table style="margin-top:6px">
        <tr><td class="sec" colspan="4">7. DETAILS OF ACTION ON APPLICATION</td></tr>
        <tr>
            <td class="sub" colspan="3">7.A &nbsp;CERTIFICATION OF LEAVE CREDITS</td>
            <td class="sub">7.B &nbsp;RECOMMENDATION</td>
        </tr>
        <tr>
            <td colspan="3"><b>As of:</b> <u>&nbsp;&nbsp;{{ $asOf }}&nbsp;&nbsp;</u></td>
            <td rowspan="6">
                <div class="drow">{!! $cb(false) !!} For approval</div>
                <div class="drow">{!! $cb(false) !!} For disapproval due to:</div>
                <div class="drow">_________________________________</div>
                <div class="drow">_________________________________</div>
                <div class="sig-app" style="margin-top:26px"><div class="line">{{ $adminFinance['name'] }}</div>Recommending Official</div>
            </td>
        </tr>
        <tr>
            <td class="hdr2">&nbsp;</td>
            <td class="hdr2">Vacation Leave</td>
            <td class="hdr2">Sick Leave</td>
        </tr>
        <tr>
            <td class="hdr2">Total Earned</td>
            <td class="num">{{ $credits['vl']['earned'] }}</td>
            <td class="num">{{ $credits['sl']['earned'] }}</td>
        </tr>
        <tr>
            <td class="hdr2">Less this application</td>
            <td class="num">{{ $credits['vl']['less'] }}</td>
            <td class="num">{{ $credits['sl']['less'] }}</td>
        </tr>
        <tr>
            <td class="hdr2">Balance</td>
            <td class="num">{{ $credits['vl']['balance'] }}</td>
            <td class="num">{{ $credits['sl']['balance'] }}</td>
        </tr>
        <tr>
            <td class="nb"></td>
            <td class="sig-cell">{{ $hrmo['name'] }}</td>
            <td class="sig-cell">{{ $adminFinance['name'] }}</td>
        </tr>
        <tr>
            <td class="nb"></td>
            <td class="sig-title-cell">HRMO II</td>
            <td class="sig-title-cell">OIC, Admin. and Finance Division</td>
        </tr>
    </table>

    {{-- 7.C / 7.D --}}
    <table style="margin-top:6px">
        <tr>
            <td class="sub" style="width:57%">7.C &nbsp;APPROVED FOR:</td>
            <td class="sub">7.D &nbsp;DISAPPROVED DUE TO:</td>
        </tr>
        <tr>
            <td>
                <div class="drow">_______ days with pay</div>
                <div class="drow">_______ days without pay</div>
                <div class="drow">_______ others (Specify) ______________________</div>
            </td>
            <td>
                <div class="drow">_________________________________</div>
                <div class="drow">_________________________________</div>
            </td>
        </tr>
        <tr>
            <td class="nb" colspan="2" style="text-align:right; padding-top:24px">
                __________________________________<br>
                <b>Assistant Regional Director</b>
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
