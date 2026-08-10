@php
    $firstAppt = $employee->appointments->first();
    $lastAppt = $employee->appointments->last();
    $serviceFrom = $employee->date_original_appointment ?? $firstAppt?->effective_from;
    $serviceTo = $lastAppt?->effective_to; // null = present

    $isActive = in_array($employee->status, ['active', null, ''], true) && $serviceTo === null;
    $isFemale = strtolower((string) $employee->gender) === 'female';
    $title = $isFemale ? 'MS.' : 'MR.';
    $pronoun = $isFemale ? 'She' : 'He';

    $status = $employee->employmentType?->name;
    $statusPhrase = $status
        ? 'on a ' . strtolower(trim((string) preg_replace('/\s*\(.*\)\s*/', ' ', $status))) . ' status'
        : '';

    $position = $employee->position?->title;
    $salary = (float) $employee->monthly_salary;
    $purposeText = (isset($purpose) && trim((string) $purpose) !== '')
        ? trim((string) $purpose)
        : 'whatever legal purpose it may serve';

    // Employment clause assembled once so the sentence punctuation stays clean.
    $fromText = $serviceFrom?->format('F d, Y') ?? '—';
    $toText = $serviceTo?->format('F d, Y') ?? '—';
    $employmentClause = $isActive
        ? 'is currently employed with the Department of Information and Communications Technology – Region 2, since ' . $fromText . ' to present'
        : 'was employed with the Department of Information and Communications Technology – Region 2, from ' . $fromText . ' to ' . $toText;
    if ($position) {
        $employmentClause .= ' and ' . ($isActive ? 'currently holding the position of' : 'held the position of') . ' ' . $position;
    }
    if ($statusPhrase) {
        $employmentClause .= ', ' . $statusPhrase;
    }
    $employmentClause .= '.';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Employment — {{ $employee->full_name }}</title>
    <style>
        /* Certificate of Employment — official DICT RO2 template, print / dompdf compatible. */
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Serif", "Times New Roman", serif;
            font-size: 12px;
            color: #000;
            margin: 0 auto;
            max-width: 8.5in;
            padding: 34px 44px 28px;
        }

        /* ---------- Letterhead ---------- */
        

        .head-rule { border: none; border-top: 1.6px solid #1b2a4a; margin: 0 0 20px; }

        .doc-title { text-align: center; font-size: 20px; font-weight: 700; margin: 0 0 4px; letter-spacing: 2px; }
        .doc-ref { text-align: center; font-size: 10px; margin-bottom: 22px; color: #333; }

        .salutation { margin: 0 0 14px; }

        .body { line-height: 1.8; text-align: justify; margin: 0 0 14px; }
        .body p { margin: 0 0 14px; }

        .issuance { margin: 22px 0 8px; }

        /* Single right-aligned signatory (official layout) */
        .signatory { text-align: right; margin-top: 46px; }
        .signatory .sig-name { font-weight: 700; font-size: 12.5px; }
        .signatory .sig-title { font-size: 11px; margin-top: 1px; }

        /* Footer */
        .footer { margin-top: 26px; }
        .footer .foot-rule { border: none; border-top: 1px solid #000; margin: 0 0 6px; }
        table.foot { width: 100%; border-collapse: collapse; font-size: 9px; color: #333; line-height: 1.5; }
        .foot td { vertical-align: top; }
        .foot .foot-right { text-align: right; }
    </style>
</head>
<body>
    {{-- Letterhead --}}
    @include('partials.letterhead')

    <hr class="head-rule">

    <div class="doc-title">CERTIFICATION</div>
    @if (isset($referenceNo))
        <div class="doc-ref">Ref. No: {{ $referenceNo }}</div>
    @endif

    <p class="salutation">TO WHOM IT MAY CONCERN:</p>

    <div class="body">
        <p>
            This is to certify that <strong>{{ $title }} {{ strtoupper($employee->full_name) }}</strong>
            {{ $employmentClause }}
        </p>

        @if ($salary > 0)
            <p>
                {{ $pronoun }} is receiving a gross monthly compensation amounting to
                <strong>{{ \App\Support\DocumentIssuer::amountInWords($salary) }}</strong>
                (Php {{ number_format($salary, 2) }}) only.
            </p>
        @endif

        <p>
            This certification is being issued upon the request of the above-named employee for
            {{ $purposeText }}.
        </p>
    </div>

    <p class="issuance">
        Issued this {{ \App\Support\DocumentIssuer::ordinalSuffix((int) now()->format('j')) }} day of
        {{ now()->format('F') }} {{ now()->format('Y') }}.
    </p>

    {{-- Signatory --}}
    <div class="signatory">
        <div class="sig-name">{{ $certifier['name'] }}</div>
        <div class="sig-title">{{ $certifier['title'] }}</div>
    </div>

    @include('partials.doc-qr')

    {{-- Footer --}}
    <div class="footer">
        <hr class="foot-rule">
        <table class="foot">
            <tr>
                <td class="foot-left">
                    DICT - Region II<br>
                    02 Bagay Road, San Gabriel Village,<br>
                    Tuguegarao City, Cagayan 3500
                </td>
                <td class="foot-right">
                    https://www.dict.gov.ph<br>
                    region2@dict.gov.ph<br>
                    (078) 8251624<br>
                    Certificate of Employment | Page 1 of 1
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
