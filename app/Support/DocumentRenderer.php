<?php

namespace App\Support;

use App\Models\DocumentRequest;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders any requestable document (COE, Service Record, Leave Balances,
 * No Pending Case, DTR) to PDF bytes with a given reference number. Used by
 * both the document-issuance action and the employee download path, so an
 * issued document regenerates deterministically with its reference.
 */
class DocumentRenderer
{
    public static function render(DocumentRequest $documentRequest, string $referenceNo): string
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
}
