<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Monthly contribution remittance tracking (GSIS, PhilHealth, PAG-IBIG, BIR).
 * Summaries derive from payroll_items; this tracks the remittance lifecycle.
 */
class Remittance extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REMITTED = 'remitted';
    public const STATUS_VERIFIED = 'verified';

    public const AGENCIES = ['GSIS', 'PHILHEALTH', 'PAGIBIG', 'BIR'];

    protected $fillable = [
        'agency', 'period_from', 'period_to',
        'employee_share_total', 'employer_share_total', 'grand_total',
        'status', 'reference_no', 'remitted_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'employee_share_total' => 'decimal:2',
            'employer_share_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'remitted_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
