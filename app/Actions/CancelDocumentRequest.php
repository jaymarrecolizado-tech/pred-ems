<?php

namespace App\Actions;

use App\Models\DocumentRequest;
use App\Support\Audit;

/**
 * Cancel a pending document request (employee-initiated). State guards are
 * the controller's responsibility.
 */
class CancelDocumentRequest
{
    public function handle(DocumentRequest $documentRequest): ActionResult
    {
        $documentRequest->update([
            'status' => DocumentRequest::STATUS_CANCELED,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        Audit::record('document_request_canceled', $documentRequest, [], $documentRequest->toArray());

        return ActionResult::ok('Request canceled.');
    }
}
