<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certification of No Pending Case — {{ $employee->full_name }}</title>
    <style>
        /* Certification of No Pending Case — print / dompdf compatible. */
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Serif", "Times New Roman", serif;
            font-size: 12px;
            color: #000;
            margin: 0 auto;
            max-width: 8.5in;
            padding: 34px 44px;
        }

        

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
    @include('partials.letterhead', ['mb' => 22])

    <div class="doc-title">CERTIFICATION OF NO PENDING CASE</div>

    @if (isset($referenceNo))
        <div class="ref-date">Ref. No: {{ $referenceNo }}<br>{{ now()->format('F d, Y') }}</div>
    @endif

    <p class="salutation">TO WHOM IT MAY CONCERN:</p>

    <p class="body">
        This is to certify that <strong>{{ $employee->full_name }}</strong>
        @if ($employee->position?->title), holding the position of
        <strong>{{ $employee->position->title }}</strong>@endif,
        @if ($employee->division?->name) under the {{ $employee->division->name }}@endif,
        has <strong>no pending administrative, criminal, or civil case</strong>
        against him/her as of the date of this certification, based on the
        records of this Office.
    </p>

    <p class="body">
        This certification is issued upon the request of the above-named employee
        for whatever legal purpose it may serve.
    </p>

    <p class="closing">Given this {{ now()->format('F j, Y') }} at Tuguegarao City, Cagayan, Philippines.</p>

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
