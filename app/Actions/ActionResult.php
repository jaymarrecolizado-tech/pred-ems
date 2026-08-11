<?php

namespace App\Actions;

/**
 * Outcome of a business action (e.g. ApproveLeave, IssueDocumentRequest,
 * FinalizePayroll).
 *
 * `success` tells the controller whether to flash `success` or `error`;
 * `message` is the human-readable summary; `errors` carries field-scoped
 * errors when the failure maps to a specific form field; `data` is an
 * optional payload (the created record, counts, reference number…).
 */
final class ActionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $errors = [],
        public readonly mixed $data = null,
    ) {}

    public static function ok(string $message, mixed $data = null): self
    {
        return new self(true, $message, data: $data);
    }

    public static function fail(string $message, array $errors = []): self
    {
        return new self(false, $message, errors: $errors);
    }
}
