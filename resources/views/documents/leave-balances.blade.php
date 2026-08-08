@php
    $balances = $employee->leaveBalances();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Leave Balances — {{ $employee->full_name }}</title>
    <style>
        /* Certificate of Leave Balances — print / dompdf compatible. */
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Serif", "Times New Roman", serif;
            font-size: 12px;
            color: #000;
            margin: 0 auto;
            max-width: 8.5in;
            padding: 34px 44px;
        }

        table.letterhead { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
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
        .letterhead h1 { margin: 0; font-size: 16px; letter-spacing: .4px; color: #1b2a4a; text-transform: uppercase; }
        .letterhead .sub { font-size: 13px; color: #1b2a4a; margin-top: 2px; }
        .logo { text-align: center; font-size: 10px; font-weight: 700; color: #1b2a4a; }

        .doc-title { text-align: center; font-size: 20px; font-weight: 700; margin: 0 0 8px; }
        .ref-date { text-align: right; font-size: 10.5px; margin-bottom: 20px; }
        .salutation { margin: 0 0 12px; }
        .body { line-height: 1.75; text-align: justify; margin: 0 0 12px; }
        .closing { margin-top: 26px; }

        table.balances { width: 100%; border-collapse: collapse; margin: 14px 0; }
        table.balances th, table.balances td { border: 1px solid #000; padding: 6px 8px; }
        table.balances th { background: #f2f2f2; font-size: 11px; text-align: center; }
        table.balances td { font-size: 11px; }
        table.balances td.r { text-align: right; }

        .signatures { margin-top: 30px; }
        .signatures table { width: 100%; border-collapse: collapse; }
        .signatures td { width: 50%; padding: 0 10px; vertical-align: top; }
        .sig-label { font-size: 11px; margin-bottom: 30px; }
        .sig-name { border-top: 1px solid #000; padding-top: 4px; font-weight: 700; font-size: 12px; }
        .sig-title { font-size: 10px; margin-top: 1px; }
        .ref-no { text-align: right; font-size: 9px; margin-top: 14px; }
    </style>
</head>
<body>
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

    <div class="doc-title">CERTIFICATE OF LEAVE BALANCES</div>

    @if (isset($referenceNo))
        <div class="ref-date">Ref. No: {{ $referenceNo }}<br>{{ $asOf->format('F d, Y') }}</div>
    @endif

    <p class="salutation">TO WHOM IT MAY CONCERN:</p>

    <p class="body">
        This is to certify that <strong>{{ $employee->full_name }}</strong>
        @if ($employee->position?->title), holding the position of
        <strong>{{ $employee->position->title }}</strong>@endif,
        as of <strong>{{ $asOf->format('F d, Y') }}</strong>, has the following
        leave credits on file with this Office:
    </p>

    <table class="balances">
        <thead>
            <tr><th>Leave Type</th><th style="width:30%">Balance (days)</th></tr>
        </thead>
        <tbody>
            @forelse ($balances as $row)
                <tr>
                    <td>{{ $row->leave_type->name }}</td>
                    <td class="r">{{ number_format($row->balance, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No leave credits on file.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="body">
        This certification is issued upon the request of the above-named employee
        for whatever legal purpose it may serve.
    </p>

    <p class="closing">Given this {{ $asOf->format('F j, Y') }} at Tuguegarao City, Cagayan, Philippines.</p>

    <div class="signatures">
        <table>
            <tr>
                <td>
                    <div class="sig-label">Prepared by:</div>
                    <div class="sig-name">{{ $preparer['name'] }}</div>
                    <div class="sig-title">{{ $preparer['title'] }}</div>
                </td>
                <td>
                    <div class="sig-label">Certified true and correct:</div>
                    <div class="sig-name">{{ $certifier['name'] }}</div>
                    <div class="sig-title">{{ $certifier['title'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if (isset($referenceNo))
        <div class="ref-no">Ref. No: {{ $referenceNo }}</div>
    @endif
</body>
</html>
