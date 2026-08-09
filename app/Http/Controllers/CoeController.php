<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Employee;
use App\Support\DocumentIssuer;
use App\Support\DocumentQr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Certificate of Employment (COE) — the second Phase 3 official document.
 * Issued with a COE-YYYY-NNNN reference number and tracked in `documents`.
 */
class CoeController extends Controller
{
    /**
     * On-screen preview of the COE letter.
     */
    public function show(Employee $employee): View
    {
        $this->authorizeAccess($employee);

        return view('documents.coe', [
            'employee' => $employee->load(['employmentType', 'position', 'division', 'appointments']),
            'preparer' => DocumentIssuer::preparer(),
            'certifier' => DocumentIssuer::certifier(),
        ]);
    }

    /**
     * Generate and download the COE PDF, recording the issuance in the
     * `documents` table. Rendered before recording, retried on duplicate
     * reference numbers — same pattern as the Service Record.
     */
    public function download(Employee $employee): Response
    {
        $this->authorizeAccess($employee);

        $employee->load(['employmentType', 'position', 'division', 'appointments']);

        $filename = 'Certificate_of_Employment_' . str_replace([' ', '.'], '_', $employee->full_name) . '.pdf';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $referenceNo = DocumentIssuer::nextReferenceNo('COE');

            $pdf = Pdf::loadView('documents.coe', [
                'employee' => $employee,
                'preparer' => DocumentIssuer::preparer(),
                'certifier' => DocumentIssuer::certifier(),
                'referenceNo' => $referenceNo,
                'qrDataUri' => DocumentQr::dataUri($referenceNo),
            ])->setPaper('a4', 'portrait');

            $pdfOutput = $pdf->output();

            try {
                Document::create([
                    'employee_id' => $employee->id,
                    'document_type' => 'certificate_of_employment',
                    'reference_no' => $referenceNo,
                    'remarks' => 'Certificate of Employment',
                    'generated_by' => auth()->id(),
                    'generated_at' => now(),
                ]);
            } catch (QueryException $e) {
                if ((int) $e->errorInfo[1] !== 1062) {
                    throw $e;
                }
                continue;
            }

            return response($pdfOutput)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        abort(500, 'Unable to issue a Certificate of Employment at this time.');
    }

    /**
     * Admin/HR may issue for any employee; the `employee` role only for self.
     */
    private function authorizeAccess(Employee $employee): void
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['admin', 'hr']) && $employee->user_id !== $user->id) {
            abort(403, 'You do not have permission to access this document.');
        }
    }
}
