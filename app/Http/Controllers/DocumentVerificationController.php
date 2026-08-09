<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\View\View;

/**
 * Public document-authenticity page — the destination of the QR code printed
 * on every official PDF. Deliberately unauthenticated: anyone in possession
 * of a reference number (e.g. a bank or another agency verifying a COE) can
 * confirm the issuance against the ledger.
 */
class DocumentVerificationController extends Controller
{
    public function show(string $referenceNo): View
    {
        $document = Document::with(['employee', 'generatedBy'])
            ->where('reference_no', $referenceNo)
            ->firstOrFail();

        return view('documents.verification', ['document' => $document]);
    }
}
