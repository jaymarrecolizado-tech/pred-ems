<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveApplication extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'date_from', 'date_to', 'days_applied',
        'reason', 'contact_during_leave', 'commutation_requested',
        'status', 'approver_id', 'approved_at', 'denial_reason', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'days_applied' => 'decimal:2',
            'commutation_requested' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                          */
    /* ------------------------------------------------------------------ */

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'badge-green',
            'rejected' => 'badge-red',
            'cancelled' => 'badge-gray',
            default => 'badge-amber',
        };
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
