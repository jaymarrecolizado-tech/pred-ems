<?php

namespace App\Actions;

use App\Models\DocumentRequest;
use App\Notifications\DocumentRequestRejectedNotification;
use App\Support\Audit;
use App\Support\Notifier;

/**
 * Reject a pending document request with the HR reason, audit it and notify
 * the employee.
 */
class RejectDocumentRequest
{
    public function handle(DocumentRequest $documentRequest, string $rejectionReason): ActionResult
    {
        $documentRequest->update([
            'status' => DocumentRequest::STATUS_REJECTED,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
            'rejection_reason' => $rejectionReason,
        ]);

        Audit::record('document_request_rejected', $documentRequest, [], $documentRequest->toArray());

        if ($documentRequest->employee->user) {
            Notifier::send($documentRequest->employee->user, new DocumentRequestRejectedNotification($documentRequest));
        }

        return ActionResult::ok('Document request rejected.');
    }
}
