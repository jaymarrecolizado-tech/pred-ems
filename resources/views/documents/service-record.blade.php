<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Record — {{ $employee->full_name }}</title>
    <style>
        /* CSC Service Record (CS Form 212) — print / dompdf compatible. */
        @page { margin: 4.5mm 5mm; }
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Serif", "Times New Roman", serif;
            font-size: 11px;
            color: #000;
            margin: 0 auto;
            max-width: 8.5in;
            padding: 4px 8px;
        }
        .border {
            border: 2px solid #000;
            padding: 4px 6px 4px;
        }

        /* ---------- Letterhead (table layout — dompdf has no flexbox/transform) ---------- */
        

        .doc-title { text-align: center; font-size: 16px; font-weight: 700; margin: 2px 0 4px; }

        /* ---------- Personal info grid ---------- */
        table.personal { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
        table.personal td {
            border: 1px solid #000;
            padding: 1.5px 5px;
            height: 16px;
            vertical-align: middle;
        }
        table.personal .lbl {
            width: 70px;
            text-align: center;
            font-size: 8px;
            font-weight: 700;
            background: #f2f2f2;
            vertical-align: top;
            padding-top: 3px;
        }
        table.personal .val { font-size: 10.5px; }
        table.personal .birth-lbl { font-size: 8px; }
        .maiden-note { font-size: 8px; color: #000; font-style: italic; }

        /* ---------- Certification ---------- */
        .certification {
            font-style: italic;
            text-align: justify;
            text-indent: 24px;
            margin: 3px 0;
            line-height: 1.2;
        }

        /* ---------- Main table ---------- */
        table.record { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.record th, table.record td { border: 1px solid #000; padding: 1px 2.5px; vertical-align: top; line-height: 1.05; word-wrap: break-word; overflow-wrap: break-word; }
        table.record thead th {
            text-align: center;
            font-size: 8.5px;
            background: #f2f2f2;
        }
        table.record thead th.main { font-size: 8.5px; }
        table.record thead th.col { font-size: 7px; padding: 1.5px 2px; }
        table.record tbody td { font-size: 8px; }
        table.record .c { text-align: center; }
        table.record .r { text-align: right; }
        .nothing-follows {
            text-align: center;
            padding: 2px 2px;
            font-style: italic;
            font-size: 8px;
        }
        .nothing-follows span {
            display: block;
            letter-spacing: .2px;
            word-spacing: 4px;
        }

        /* Column widths (11 data columns + spacing) */
        .col-from { width: 7.5%; }
        .col-to   { width: 7.5%; }
        .col-des  { width: 14%; }
        .col-status { width: 7%; }
        .col-salary { width: 9.5%; }
        .col-office { width: 15%; }
        .col-branch { width: 7%; }
        .col-lv   { width: 7.5%; }
        .col-sep-date { width: 7%; }
        .col-sep-cause { width: 7.5%; }
        .col-remarks  { width: 11.5%; }

        /* ---------- Footer ---------- */
        .compliance { font-size: 8.5px; margin: 3px 0 1px; text-align: justify; }
        .signatures { margin-top: 4px; }
        .signatures table { width: 100%; border-collapse: collapse; }
        .signatures td { width: 50%; padding: 0 10px; vertical-align: top; }
        .sig-label { font-size: 9px; margin-bottom: 6px; }
        .sig-name { border-top: 1px solid #000; padding-top: 3px; font-weight: 700; font-size: 10.5px; }
        .sig-title { font-size: 9.5px; margin-top: 1px; }
        .ref-no { text-align: right; font-size: 8.5px; margin-top: 4px; }
    </style>
</head>
<body>
<div class="border">
    {{-- Letterhead --}}
    @include('partials.letterhead', ['compact' => true, 'mb' => 2])

    <div class="doc-title">SERVICE RECORD</div>

    {{-- Personal information --}}
    <table class="personal">
        <tr>
            <td class="lbl">(Surname)</td>
            <td class="val" style="width:26%">{{ $employee->last_name ?? '' }}</td>
            <td class="lbl">(Name)</td>
            <td class="val" style="width:26%">{{ $employee->first_name ?? '' }}</td>
            <td class="lbl">(Middle Name)</td>
            <td class="val" style="width:26%">{{ $employee->middle_name ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">(Maiden<br>Name)</td>
            <td class="val">{{ $employee->maiden_name ?? '' }}</td>
            <td class="lbl birth-lbl">(Date)</td>
            <td class="val" colspan="1">{{ $employee->birth_date ? $employee->birth_date->format('F d, Y') : '' }}</td>
            <td class="lbl birth-lbl">(Place)</td>
            <td class="val">{{ $employee->birth_place ?? '' }}</td>
        </tr>
        <tr>
            <td colspan="6" class="maiden-note">(if married woman, give also maiden name) · Date herein should be checked from Birth Certificate or other reliable documents</td>
        </tr>
    </table>

    {{-- Certification --}}
    <div class="certification">
        THIS IS TO CERTIFY that the employee named above actually rendered services in
        the office as shown by the service record below, each item of which is supported
        by appointment and other papers actually issued by this office and approved by
        the authorities concerned.
    </div>

    {{-- Record of appointment --}}
    <table class="record">
        <colgroup>
            <col class="col-from">
            <col class="col-to">
            <col class="col-des">
            <col class="col-status">
            <col class="col-salary">
            <col class="col-office">
            <col class="col-branch">
            <col class="col-lv">
            <col class="col-sep-date">
            <col class="col-sep-cause">
            <col class="col-remarks">
        </colgroup>
        <thead>
            <tr>
                <th class="main col-from" colspan="2">SERVICE</th>
                <th class="main" colspan="6">RECORD OF APPOINTMENT</th>
                <th class="main" colspan="2">SEPARATION</th>
                <th class="main col-remarks" rowspan="2">REMARKS</th>
            </tr>
            <tr>
                <th class="col">Inclusive Dates<br>From</th>
                <th class="col">To</th>
                <th class="col col-des" style="width:14%">Designation</th>
                <th class="col">Status</th>
                <th class="col">Annual Salary</th>
                <th class="col col-office">Name of Office</th>
                <th class="col">Branch</th>
                <th class="col">LV/AB<br>w/o pay</th>
                <th class="col">DATE</th>
                <th class="col">CAUSE</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employee->appointments as $appointment)
                <tr>
                    <td class="c">{{ $appointment->effective_from->format('m/d/Y') }}</td>
                    <td class="c">{{ $appointment->effective_to?->format('m/d/Y') ?? 'Present' }}</td>
                    <td style="width:14%">{{ $appointment->position?->title ?? '—' }}</td>
                    <td class="c">{{ $appointment->employmentType?->name ?? '—' }}</td>
                    <td class="r">{{ $appointment->monthly_salary ? '₱' . number_format((float) $appointment->monthly_salary * 12, 2) : '' }}</td>
                    <td>DEPT OF INFO AND<br>COMMUNICATIONS TECH</td>
                    <td class="c">Nat'l</td>
                    <td class="c">None</td>
                    <td class="c">None</td>
                    <td class="c">None</td>
                    <td style="width:12.5%">{{ $appointment->remarks ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="11" style="text-align:center">No appointment records on file.</td></tr>
            @endforelse
            <tr>
                <td colspan="11" class="nothing-follows">
                    <span>*** Nothing Follows *** *** Nothing Follows *** *** Nothing Follows *** *** Nothing Follows *** *** Nothing Follows ***</span>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Compliance footer --}}
    <div class="compliance">
        Issued in compliance with Executive Order No. 54 dated August 10, 1954 and in
        accordance with circular No. 58 dated August 10, 1954 of the system.
    </div>

    {{-- Signatures --}}
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

    @include('partials.doc-qr', ['qrMargin' => 2, 'qrSize' => 40])
</div>
</body>
</html>
