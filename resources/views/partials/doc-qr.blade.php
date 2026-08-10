{{-- QR authenticity block for official documents — dompdf-safe (table only). --}}
@if (! empty($qrDataUri) && ! empty($referenceNo))
    <table class="doc-qr" style="width:100%; border-collapse:collapse; margin-top:{{ $qrMargin ?? 16 }}px; border-top:1px solid #999; padding-top:6px">
        <tr>
            <td style="width:{{ ($qrSize ?? 70) + 8 }}px; vertical-align:middle; padding-top:6px">
                <img src="{{ $qrDataUri }}" style="width:{{ $qrSize ?? 70 }}px; height:{{ $qrSize ?? 70 }}px" alt="QR verification code">
            </td>
            <td style="vertical-align:middle; padding-top:8px; font-size:8.5px; color:#444; line-height:1.55">
                <strong style="color:#1b2a4a; font-size:9.5px">VERIFY THIS DOCUMENT</strong><br>
                Scan the QR code or visit <strong>{{ url('/verify/' . rawurlencode($referenceNo)) }}</strong>
                to confirm this document was issued by the Department of Information and
                Communications Technology, Regional Office No. 2.<br>
                <span style="color:#666">Reference No: {{ $referenceNo }}</span>
            </td>
        </tr>
    </table>
@endif
