<?php

namespace App\Http\Controllers;

use App\Actions\CancelDocumentRequest;
use App\Actions\IssueDocumentRequest;
use App\Actions\RejectDocumentRequest;
use App\Actions\SubmitDocumentRequest;
use App\Http\Requests\RejectDocumentRequestRequest;
use App\Http\Requests\StoreDocumentRequestRequest;
use App\Models\DocumentRequest;
use App\Support\DocumentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Self-service document requests (Phase 3.5).
 *
 * Employees request any of the requestable documents (COE, Service Record,
 * Leave Balances, No Pending Case, DTR); admin/HR fulfill them from a queue —
 * issuing mints a reference number, generates the PDF, records the issuance
 * in `documents`, and the employee can then download the official copy. The
 * business operations live in App\Actions\* (SubmitDocumentRequest,
 * IssueDocumentRequest, …); this controller handles validation + flashes.
 */
class DocumentRequestController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Self-service */
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

        $result = (new SubmitDocumentRequest)->handle($employee, $request->validated());

        if (! $result->success) {
            return back()->withErrors($result->errors)->withInput();
        }

        return redirect()->route('documents.requests')
            ->with('success', $result->message);
    }

    public function cancel(DocumentRequest $documentRequest): RedirectResponse
    {
        $this->authorizeAccess($documentRequest);
        abort_unless($documentRequest->isPending(), 409, 'Only pending requests can be canceled.');

        $result = (new CancelDocumentRequest)->handle($documentRequest);

        return back()->with('success', $result->message);
    }

    /* ------------------------------------------------------------------ */
    /*  HR queue */
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
     * Issue the requested document — the reference minting, PDF render,
     * `documents` ledger row and request update live in
     * App\Actions\IssueDocumentRequest.
     */
    public function issue(DocumentRequest $documentRequest): RedirectResponse
    {
        abort_unless($documentRequest->isPending(), 409, 'This request was already processed.');

        $result = (new IssueDocumentRequest)->handle($documentRequest);

        if (! $result->success) {
            abort(500, $result->message);
        }

        return back()->with('success', $result->message);
    }

    public function reject(RejectDocumentRequestRequest $request, DocumentRequest $documentRequest): RedirectResponse
    {
        abort_unless($documentRequest->isPending(), 409, 'This request was already processed.');

        $result = (new RejectDocumentRequest)->handle($documentRequest, $request->validated()['rejection_reason']);

        return back()->with('success', $result->message);
    }

    /* ------------------------------------------------------------------ */
    /*  Download */
    /* ------------------------------------------------------------------ */

    /**
     * Official copy of an issued document, regenerated with its reference.
     */
    public function download(DocumentRequest $documentRequest): Response
    {
        $this->authorizeAccess($documentRequest);
        abort_unless($documentRequest->status === DocumentRequest::STATUS_ISSUED, 404, 'This request has not been issued yet.');

        $pdfOutput = DocumentRenderer::render($documentRequest, $documentRequest->reference_no);

        $filename = str_replace(' ', '_', ucwords(str_replace('_', ' ', $documentRequest->document_type)))
            .'_'.str_replace([' ', '.'], '_', $documentRequest->employee->full_name).'.pdf';

        return response($pdfOutput)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers */
    /* ------------------------------------------------------------------ */

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
