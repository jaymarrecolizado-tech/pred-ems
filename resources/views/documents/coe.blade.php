@php
    $firstAppt = $employee->appointments->first();
    $lastAppt = $employee->appointments->last();
    $serviceFrom = $employee->date_original_appointment ?? $firstAppt?->effective_from;
    $serviceTo = $lastAppt?->effective_to; // null = present
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Employment — {{ $employee->full_name }}</title>
    <style>
        /* Certificate of Employment — print / dompdf compatible. */
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Serif", "Times New Roman", serif;
            font-size: 12px;
            color: #000;
            margin: 0 auto;
            max-width: 8.5in;
            padding: 34px 44px;
        }

        /* ---------- Letterhead ---------- */
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
        .letterhead h1 {
            margin: 0;
            font-size: 16px;
            letter-spacing: .4px;
            color: #1b2a4a;
            text-transform: uppercase;
        }
        .letterhead .sub { font-size: 13px; color: #1b2a4a; margin-top: 2px; }
        .logo { text-align: center; font-size: 10px; font-weight: 700; color: #1b2a4a; }

        .doc-title { text-align: center; font-size: 20px; font-weight: 700; margin: 0 0 8px; }
        .ref-date { text-align: right; font-size: 10.5px; margin-bottom: 20px; }
        .salutation { margin: 0 0 12px; }

        .body { line-height: 1.75; text-align: justify; margin: 0 0 12px; }
        .closing { margin-top: 26px; }

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

    <div class="doc-title">CERTIFICATE OF EMPLOYMENT</div>

    @if (isset($referenceNo))
        <div class="ref-date">Ref. No: {{ $referenceNo }}<br>{{ now()->format('F d, Y') }}</div>
    @endif

    <p class="salutation">TO WHOM IT MAY CONCERN:</p>

    <p class="body">
        This is to certify that <strong>{{ $employee->full_name }}</strong>
        @if ($employee->position?->title), holding the position of
        <strong>{{ $employee->position->title }}</strong>@endif
        @if ($employee->division?->name) under the {{ $employee->division->name }}@endif,
        has been employed in the Department of Information and Communications
        Technology, Regional Office No. 2 from
        <strong>{{ $serviceFrom?->format('F d, Y') ?? '—' }}</strong> up to
        <strong>{{ $serviceTo?->format('F d, Y') ?? 'the present' }}</strong>.
    </p>

    <p class="body">
        This certification is issued upon the request of the above-named employee
        for whatever legal purpose it may serve.
    </p>

    <p class="closing">Given this {{ now()->format('F j, Y') }} at Tuguegarao City, Cagayan, Philippines.</p>

    {{-- Signature blocks --}}
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

    @include('partials.doc-qr')
</body>
</html>
