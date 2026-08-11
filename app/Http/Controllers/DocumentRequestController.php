<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectDocumentRequestRequest;
use App\Http\Requests\StoreDocumentRequestRequest;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Notifications\DocumentRequestIssuedNotification;
use App\Notifications\DocumentRequestRejectedNotification;
use App\Notifications\DocumentRequestSubmittedNotification;
use App\Support\Audit;
use App\Support\DocumentIssuer;
use App\Support\DocumentQr;
use App\Support\Dtr;
use App\Support\Notifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Self-service document requests (Phase 3.5).
 *
 * Employees request any of the requestable documents (COE, Service Record,
 * Leave Balances, No Pending Case, DTR); admin/HR fulfill them from a queue —
 * issuing mints a reference number, generates the PDF, records the issuance
 * in `documents`, and the employee can then download the official copy.
 */
class DocumentRequestController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Self-service                                                       */
    /* ------------------------------------------------------------------ */

    public function index(): View
    {
        $employee = auth()->user()->employee;
        abort_unless($employee, 403, 'No employee 201-file record linked to this account.');

        $requests = DocumentRequest::query()
            ->where('employee_id', $employee->id)
            ->with('processedBy')
            ->latest()
            ->paginate(12);

        return view('documents.requests.index', compact('requests'));
    }

    public function create(): View
    {
        return view('documents.requests.create');
    }

    public function store(StoreDocumentRequestRequest $request): RedirectResponse
    {
        $employee = auth()->user()->employee;
        abort_unless($employee, 403, 'No employee 201-file record linked to this account.');

        $validated = $request->validated();

        if ($validated['document_type'] === 'dtr' && empty($validated['period'])) {
            return back()->withErrors(['period' => 'Choose the month for the Daily Time Record.'])
                ->withInput();
        }

        // Guard against duplicate pending requests for the same document.
        $duplicate = DocumentRequest::query()
            ->where('employee_id', $employee->id)
            ->where('document_type', $validated['document_type'])
            ->where('status', DocumentRequest::STATUS_PENDING)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['document_type' => 'You already have a pending request for this document. Wait for HR to process it.'])
                ->withInput();
        }

        $documentRequest = DocumentRequest::create([
            'employee_id' => $employee->id,
            'document_type' => $validated['document_type'],
            'purpose' => $validated['purpose'],
            'period' => $validated['period'] ?? null,
            'status' => DocumentRequest::STATUS_PENDING,
        ]);

        Audit::record('document_requested', $documentRequest, [], $documentRequest->toArray());

        // Alert the HR queue reviewers.
        Notifier::send(Notifier::hrUsers(), new DocumentRequestSubmittedNotification($documentRequest));

        return redirect()->route('documents.requests')
            ->with('success', 'Document request submitted for HR processing.');
    }

    public function cancel(DocumentRequest $documentRequest): RedirectResponse
    {
        $this->authorizeAccess($documentRequest);
        abort_unless($documentRequest->isPending(), 409, 'Only pending requests can be canceled.');

        $documentRequest->update([
            'status' => DocumentRequest::STATUS_CANCELED,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        Audit::record('document_request_canceled', $documentRequest, [], $documentRequest->toArray());

        return back()->with('success', 'Request canceled.');
    }

    /* ------------------------------------------------------------------ */
    /*  HR queue                                                           */
    /* ------------------------------------------------------------------ */

    public function queue(Request $request): View
    {
        $status = $request->string('status', 'pending');

        $requests = DocumentRequest::query()
            ->with(['employee.position', 'employee.division', 'processedBy'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('documents.requests.queue', [
            'requests' => $requests,
            'status' => $status,
            'statuses' => [
                'pending' => 'Pending', 'issued' => 'Issued',
                'rejected' => 'Rejected', 'canceled' => 'Canceled', 'all' => 'All',
            ],
        ]);
    }

    /**
     * Issue the requested document: mint a reference number, render the PDF,
     * record the issuance in `documents`, and mark the request issued.
     */
    public function issue(DocumentRequest $documentRequest): RedirectResponse
    {
        abort_unless($documentRequest->isPending(), 409, 'This request was already processed.');

        $employee = $documentRequest->employee;
        $referenceNo = null;
        $document = null;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $referenceNo = DocumentIssuer::nextReferenceNo($documentRequest->prefix);
            $this->renderPdf($documentRequest, $referenceNo);

            try {
                $document = Document::create([
                    'employee_id' => $employee->id,
                    'document_type' => $documentRequest->document_type,
                    'reference_no' => $referenceNo,
                    'remarks' => $documentRequest->type_label . ' — request #' . $documentRequest->id,
                    'generated_by' => auth()->id(),
                    'generated_at' => now(),
                ]);
                break;
            } catch (QueryException $e) {
                if ((int) $e->errorInfo[1] !== 1062) {
                    throw $e;
                }
            }
        }

        // The ledger row is the proof of issuance — never mark the request
        // issued without one (all reference numbers collided).
        if (! $document) {
            abort(500, 'Unable to issue this document at this time. Please try again.');
        }

        DB::transaction(function () use ($documentRequest, $referenceNo) {
            $documentRequest->update([
                'status' => DocumentRequest::STATUS_ISSUED,
                'reference_no' => $referenceNo,
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);
        });

        Audit::record('document_issued', $documentRequest, [], [
            'document_type' => $documentRequest->document_type,
            'reference_no' => $referenceNo,
        ]);

        // Notify the requesting employee (in-system + email + SMS when on file).
        if ($employee->user) {
            Notifier::send($employee->user, new DocumentRequestIssuedNotification($documentRequest));
        }

        return back()->with('success', $documentRequest->type_label . " issued — Ref. {$referenceNo}.");
    }

    public function reject(RejectDocumentRequestRequest $request, DocumentRequest $documentRequest): RedirectResponse
    {
        abort_unless($documentRequest->isPending(), 409, 'This request was already processed.');

        $validated = $request->validated();

        $documentRequest->update([
            'status' => DocumentRequest::STATUS_REJECTED,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        Audit::record('document_request_rejected', $documentRequest, [], $documentRequest->toArray());

        if ($documentRequest->employee->user) {
            Notifier::send($documentRequest->employee->user, new DocumentRequestRejectedNotification($documentRequest));
        }

        return back()->with('success', 'Document request rejected.');
    }

    /* ------------------------------------------------------------------ */
    /*  Download                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Official copy of an issued document, regenerated with its reference.
     */
    public function download(DocumentRequest $documentRequest): Response
    {
        $this->authorizeAccess($documentRequest);
        abort_unless($documentRequest->status === DocumentRequest::STATUS_ISSUED, 404, 'This request has not been issued yet.');

        $pdfOutput = $this->renderPdf($documentRequest, $documentRequest->reference_no);

        $filename = str_replace(' ', '_', ucwords(str_replace('_', ' ', $documentRequest->document_type)))
            . '_' . str_replace([' ', '.'], '_', $documentRequest->employee->full_name) . '.pdf';

        return response($pdfOutput)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Render the requested document to PDF bytes with the given reference.
     */
    private function renderPdf(DocumentRequest $documentRequest, string $referenceNo): string
    {
        $employee = $documentRequest->employee->load([
            'position', 'division', 'employmentType', 'appointments.position', 'appointments.employmentType',
        ]);

        $view = match ($documentRequest->document_type) {
            'certificate_of_employment' => 'documents.coe',
            'service_record' => 'documents.service-record',
            'leave_balances' => 'documents.leave-balances',
            'no_pending_case' => 'documents.no-pending-case',
            'dtr' => 'documents.dtr',
            default => abort(422, 'Unsupported document type.'),
        };

        if ($documentRequest->document_type === 'dtr') {
            [$year, $month] = array_map('intval', explode('-', $documentRequest->period ?? now()->format('Y-m')));

            return Pdf::loadView($view, [
                'dtr' => Dtr::build($employee, $month, $year),
                'referenceNo' => $referenceNo,
                'qrDataUri' => DocumentQr::dataUri($referenceNo),
            ])->setPaper('a4', 'portrait')->output();
        }

        $data = [
            'employee' => $employee,
            'preparer' => DocumentIssuer::preparer(),
            'certifier' => DocumentIssuer::certifier(),
            'referenceNo' => $referenceNo,
            'qrDataUri' => DocumentQr::dataUri($referenceNo),
        ];

        // The COE letter quotes the purpose the employee stated when requesting.
        if ($documentRequest->document_type === 'certificate_of_employment') {
            $data['purpose'] = $documentRequest->purpose;
        }

        if ($documentRequest->document_type === 'leave_balances') {
            $data['asOf'] = now();
        }

        return Pdf::loadView($view, $data)->setPaper('a4', 'portrait')->output();
    }

    /**
     * Admin/HR may view any request; the `employee` role only their own.
     */
    private function authorizeAccess(DocumentRequest $documentRequest): void
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['admin', 'hr']) && $documentRequest->employee->user_id !== $user->id) {
            abort(403, 'You do not have permission to access this request.');
        }
    }
}
