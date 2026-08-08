<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Employee;
use App\Models\Position;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ServiceRecordController extends Controller
{
    /**
     * On-screen preview of the CSC Service Record (CS Form 212).
     */
    public function show(Employee $employee): View
    {
        $this->authorizeAccess($employee);

        return view('documents.service-record', [
            'employee' => $employee->load(['employmentType', 'position', 'appointments.position', 'appointments.employmentType']),
            'preparer' => $this->preparer(),
            'certifier' => $this->certifier(),
        ]);
    }

    /**
     * Generate and download the Service Record PDF (dompdf), recording the
     * issued document in the `documents` table with a reference number.
     *
     * The PDF is rendered before the issuance is recorded so a rendering
     * failure never leaves an orphan document row; concurrent duplicate
     * reference numbers are retried with a fresh number.
     */
    public function download(Employee $employee): Response
    {
        $this->authorizeAccess($employee);

        $employee->load(['employmentType', 'position', 'appointments.position', 'appointments.employmentType']);

        $filename = 'Service_Record_' . str_replace([' ', '.'], '_', $employee->full_name) . '.pdf';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $referenceNo = $this->nextReferenceNo();

            $pdf = Pdf::loadView('documents.service-record', [
                'employee' => $employee,
                'preparer' => $this->preparer(),
                'certifier' => $this->certifier(),
                'referenceNo' => $referenceNo,
            ])->setPaper('a4', 'portrait');

            $pdfOutput = $pdf->output();

            try {
                Document::create([
                    'employee_id' => $employee->id,
                    'document_type' => 'service_record',
                    'reference_no' => $referenceNo,
                    'remarks' => 'Service Record (CS Form 212)',
                    'generated_by' => auth()->id(),
                    'generated_at' => now(),
                ]);
            } catch (QueryException $e) {
                // 1062 = MySQL duplicate entry: another request took this
                // reference number concurrently — allocate a fresh one.
                if ((int) $e->errorInfo[1] !== 1062) {
                    throw $e;
                }
                continue;
            }

            return response($pdfOutput)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        abort(500, 'Unable to issue a Service Record at this time.');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Admin/HR may view any employee's record; the `employee` role may only
     * view their own.
     */
    private function authorizeAccess(Employee $employee): void
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['admin', 'hr']) && $employee->user_id !== $user->id) {
            abort(403, 'You do not have permission to access this document.');
        }
    }

    /**
     * "Prepared by" — the HRMO / administrative officer handling HR, falling
     * back to the signed-in user.
     */
    private function preparer(): array
    {
        $position = Position::where('title', 'like', '%HRMO%')
            ->orWhere('title', 'like', 'Administrative Officer II%')
            ->first();

        $employee = $position?->employees()->first() ?? auth()->user()->employee;

        return $employee
            ? ['name' => $employee->full_name, 'title' => $employee->position?->title ?? 'HRMO']
            : ['name' => auth()->user()->name, 'title' => auth()->user()->roles()->first()?->name ?? 'HRMO'];
    }

    /**
     * "Certified true and correct" — the Chief Administrative Officer.
     */
    private function certifier(): array
    {
        $position = Position::where('title', 'like', '%Chief Administrative Officer%')->first();
        $employee = $position?->employees()->first();

        return $employee
            ? ['name' => $employee->full_name, 'title' => 'Chief Administrative Officer']
            : ['name' => 'Chief Administrative Officer', 'title' => 'Chief Administrative Officer'];
    }

    private function nextReferenceNo(): string
    {
        $prefix = 'SR-' . now()->format('Y') . '-';
        $last = Document::where('reference_no', 'like', $prefix . '%')
            ->orderByDesc('reference_no')
            ->value('reference_no');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
