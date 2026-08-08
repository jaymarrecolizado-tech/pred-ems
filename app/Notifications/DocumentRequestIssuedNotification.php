<?php

namespace App\Notifications;

use App\Models\DocumentRequest;

class DocumentRequestIssuedNotification extends HrisNotification
{
    public function __construct(DocumentRequest $request)
    {
        $label = $request->type_label;

        parent::__construct(
            title: 'Document issued',
            body: "Your {$label} has been issued — Ref. {$request->reference_no}. You can now download the official copy from My Documents.",
            url: route('documents.requests'),
            smsText: "Your {$label} (Ref. {$request->reference_no}) is ready. Download it from My Documents in the HRIS.",
        );
    }
}
