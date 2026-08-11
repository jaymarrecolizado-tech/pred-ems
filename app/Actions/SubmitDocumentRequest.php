<?php

namespace App\Actions;

use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Notifications\DocumentRequestSubmittedNotification;
use App\Support\Audit;
use App\Support\Notifier;

/**
 * Submit a document request: guard against duplicate pending requests for the
 * same document, persist it, audit it and alert the HR queue reviewers.
 */
class SubmitDocumentRequest
{
    public function handle(Employee $employee, array $validated): ActionResult
    {
        if ($validated['document_type'] === 'dtr' && empty($validated['period'])) {
            $message = 'Choose the month for the Daily Time Record.';

            return ActionResult::fail($message, ['period' => $message]);
        }

        // Guard against duplicate pending requests for the same document.
        $duplicate = DocumentRequest::query()
            ->where('employee_id', $employee->id)
            ->where('document_type', $validated['document_type'])
            ->where('status', DocumentRequest::STATUS_PENDING)
            ->exists();

        if ($duplicate) {
            $message = 'You already have a pending request for this document. Wait for HR to process it.';

            return ActionResult::fail($message, ['document_type' => $message]);
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

        return ActionResult::ok('Document request submitted for HR processing.', $documentRequest);
    }
}
