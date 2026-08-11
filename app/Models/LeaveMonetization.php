<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Vacation leave monetization record — VL credits converted to cash at
 * (monthly salary ÷ 22) per day. Every processed monetization debits the
 * append-only leave ledger (movement 'monetized') and carries a sequential
 * MO-YYYY-NNNN reference so the voucher can be tracked back to the payroll
 * cashier.
 */
class LeaveMonetization extends Model
{
    protected $fillable = [
        'employee_id', 'year', 'days', 'per_day_rate', 'gross_amount',
        'reference_no', 'remarks', 'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'days' => 'decimal:2',
            'per_day_rate' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Next sequential reference for a monetization year, e.g. MO-2026-0001.
     */
    public static function nextReferenceNo(int $year): string
    {
        $prefix = 'MO-'.$year.'-';
        $last = static::where('reference_no', 'like', $prefix.'%')
            ->orderByDesc('reference_no')
            ->value('reference_no');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
