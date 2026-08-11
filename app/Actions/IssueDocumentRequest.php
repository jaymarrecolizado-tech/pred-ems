<?php

namespace App\Actions;

use App\Models\Document;
use App\Models\DocumentRequest;
use App\Notifications\DocumentRequestIssuedNotification;
use App\Support\Audit;
use App\Support\DocumentIssuer;
use App\Support\DocumentRenderer;
use App\Support\Notifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Issue a requested document: mint a sequential reference number (retrying on
 * race collisions), render the PDF to prove it generates, record the issuance
 * in the `documents` ledger and mark the request issued — then notify the
 * requesting employee.
 */
class IssueDocumentRequest
{
    public function handle(DocumentRequest $documentRequest): ActionResult
    {
        $employee = $documentRequest->employee;
        $referenceNo = null;
        $document = null;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $referenceNo = DocumentIssuer::nextReferenceNo($documentRequest->prefix);
            DocumentRenderer::render($documentRequest, $referenceNo);

            try {
                $document = Document::create([
                    'employee_id' => $employee->id,
                    'document_type' => $documentRequest->document_type,
                    'reference_no' => $referenceNo,
                    'remarks' => $documentRequest->type_label.' — request #'.$documentRequest->id,
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
            return ActionResult::fail('Unable to issue this document at this time. Please try again.');
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

        return ActionResult::ok(
            $documentRequest->type_label." issued — Ref. {$referenceNo}.",
            ['reference_no' => $referenceNo]
        );
    }
}
