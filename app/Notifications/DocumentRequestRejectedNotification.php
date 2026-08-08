<?php

namespace App\Notifications;

use App\Models\DocumentRequest;

class DocumentRequestRejectedNotification extends HrisNotification
{
    public function __construct(DocumentRequest $request)
    {
        $reason = $request->rejection_reason ?: 'No reason was provided.';

        parent::__construct(
            title: 'Document request rejected',
            body: "Your {$request->type_label} request was not approved. Reason: {$reason}",
            url: route('documents.requests'),
            smsText: "Your {$request->type_label} request was not approved. Reason: {$reason}",
        );
    }
}
