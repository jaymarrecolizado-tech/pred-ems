{{--
    Shared DICT RO2 letterhead for PDF documents (dompdf-safe).
    Renders the official DICT seal + Bagong Pilipinas logo embedded as base64
    data URIs so no external asset resolution is needed at PDF render time.

    Params:
        $compact (bool, default false) — smaller sizing for tight documents (DTR, payslip).
        $mb (int, optional)            — letterhead bottom margin in px (auto: 6 compact / 16 full).
        $subtitle (string, optional)   — second masthead line (defaults to the department line).
--}}
@php
    $lhCompact = ($compact ?? false);
    $lhSide = $lhCompact ? 62 : 84;
    $lhSeal = $lhCompact ? 46 : 62;
    $lhLogo = $lhCompact ? 38 : 52;
    $lhLogoH = $lhCompact ? 35 : 48;
    $lhGov = $lhCompact ? 12 : 14;
    $lhDept = $lhCompact ? 10 : 12.5;
    $lhMb = $mb ?? ($lhCompact ? 6 : 16);
    $lhSubtitle = $subtitle ?? 'Department of Information and Communications Technology';

    $lhDict = 'data:image/png;base64,' . base64_encode((string) file_get_contents(public_path('img/dict_logo.png')));
    $lhBp = 'data:image/png;base64,' . base64_encode((string) file_get_contents(public_path('img/bp_logo.png')));
@endphp
<table style="width:100%; border-collapse:collapse; margin-bottom:{{ $lhMb }}px">
    <tr>
        <td style="width:{{ $lhSide }}px; vertical-align:middle; text-align:left">
            <img src="{{ $lhDict }}" alt="DICT" style="width:{{ $lhSeal }}px; height:{{ $lhSeal }}px">
        </td>
        <td style="vertical-align:middle; text-align:center">
            <div style="font-size:{{ $lhGov }}px; letter-spacing:.5px; color:#1b2a4a; font-weight:700">Republic of the Philippines</div>
            <div style="font-size:{{ $lhDept }}px; color:#1b2a4a; margin-top:1px; font-weight:700">{{ $lhSubtitle }}</div>
        </td>
        <td style="width:{{ $lhSide }}px; vertical-align:middle; text-align:right">
            <img src="{{ $lhBp }}" alt="Bagong Pilipinas" style="width:{{ $lhLogo }}px; height:{{ $lhLogoH }}px">
        </td>
    </tr>
</table>
