<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Verification — DICT RO2</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f2f5f9; color: #1a2333;
            display: flex; align-items: center; justify-content: center; padding: 24px;
        }
        .card {
            background: #fff; border-radius: 16px; box-shadow: 0 12px 40px rgba(27, 42, 74, .14);
            max-width: 520px; width: 100%; padding: 34px 38px; text-align: center;
        }
        .seal {
            width: 74px; height: 74px; margin: 0 auto 14px;
            border: 3px solid #1b2a4a; border-radius: 50%;
            color: #1b2a4a; font-weight: 700; font-size: 15px; line-height: 1.15;
            display: flex; align-items: center; justify-content: center;
        }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: #e8f7ee; color: #157347; border: 1px solid #bfe6cd;
            font-weight: 700; font-size: 13px; letter-spacing: .3px;
            padding: 7px 14px; border-radius: 999px; margin-bottom: 6px;
        }
        .badge .dot { width: 10px; height: 10px; border-radius: 50%; background: #1f9d55; }
        h1 { margin: 0 0 4px; font-size: 22px; color: #1b2a4a; }
        .sub { color: #5a6b85; font-size: 13.5px; margin-bottom: 22px; }
        .meta { border-top: 1px solid #e6ebf2; border-bottom: 1px solid #e6ebf2; padding: 14px 0; margin-bottom: 18px; }
        .meta .row { display: flex; justify-content: space-between; gap: 14px; padding: 7px 2px; font-size: 13.5px; }
        .meta .row .k { color: #5a6b85; text-align: left; }
        .meta .row .v { font-weight: 700; text-align: right; }
        .foot { color: #8a97ab; font-size: 12px; line-height: 1.6; }
        .foot a { color: #1b2a4a; }
    </style>
</head>
<body>
    <div class="card">
        <div class="seal">DICT<br>RO2</div>
        <div class="badge"><span class="dot"></span> DOCUMENT VERIFIED</div>
        <h1>Authentic Issuance Confirmed</h1>
        <div class="sub">This document was issued by the Department of Information and Communications Technology, Regional Office No. 2.</div>

        <div class="meta">
            <div class="row"><span class="k">Reference No.</span><span class="v">{{ $document->reference_no }}</span></div>
            <div class="row"><span class="k">Document</span><span class="v">{{ $document->document_type_label }}</span></div>
            <div class="row"><span class="k">Issued to</span><span class="v">{{ $document->employee?->full_name ?? '—' }}</span></div>
            <div class="row"><span class="k">Issued by</span><span class="v">{{ $document->generatedBy?->name ?? '—' }}</span></div>
            <div class="row"><span class="k">Date issued</span><span class="v">{{ $document->generated_at?->format('F d, Y · g:i A') ?? '—' }}</span></div>
        </div>

        <div class="foot">
            Verification performed {{ now()->format('F d, Y · g:i A') }}.<br>
            If you are verifying a physical copy, the reference number above must match the
            reference printed on the document. For inquiries: <a href="#">DICT Regional Office 2</a>.
        </div>
    </div>
</body>
</html>
