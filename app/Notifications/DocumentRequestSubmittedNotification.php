<?php

namespace App\Notifications;

use App\Models\DocumentRequest;

class DocumentRequestSubmittedNotification extends HrisNotification
{
    public function __construct(DocumentRequest $request)
    {
        parent::__construct(
            title: 'New document request',
            body: "{$request->employee->full_name} requested a {$request->type_label}".($request->period ? " for {$request->period}" : '').'.',
            url: route('documents.requests.queue'),
        );
    }
}
