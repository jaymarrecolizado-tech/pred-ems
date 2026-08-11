<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Employee document request + HR fulfillment (Phase 3.5 — self-service
 * document requests). Statuses: pending → issued | rejected.
 */
class DocumentRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELED = 'canceled';

    /**
     * Requestable documents with the reference-number prefix used on issue.
     */
    public const TYPES = [
        'certificate_of_employment' => ['label' => 'Certificate of Employment', 'prefix' => 'COE'],
        'service_record' => ['label' => 'Service Record (CSC Form 212)', 'prefix' => 'SR'],
        'leave_balances' => ['label' => 'Certificate of Leave Balances', 'prefix' => 'LB'],
        'no_pending_case' => ['label' => 'Certification of No Pending Case', 'prefix' => 'NPC'],
        'dtr' => ['label' => 'Daily Time Record (CSC Form 48)', 'prefix' => 'DTR'],
    ];

    protected $fillable = [
        'employee_id', 'document_type', 'purpose', 'period', 'status',
        'reference_no', 'processed_by', 'processed_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->document_type]['label'] ?? ucwords(str_replace('_', ' ', $this->document_type));
    }

    public function getPrefixAttribute(): string
    {
        return self::TYPES[$this->document_type]['prefix'] ?? 'DOC';
    }
}
