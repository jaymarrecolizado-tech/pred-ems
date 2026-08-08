<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    protected $fillable = [
        'employee_id', 'log_date', 'punch_type', 'requested_time',
        'reason', 'status', 'reviewed_by', 'reviewed_at', 'denial_reason',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'requested_time' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getPunchTypeLabelAttribute(): string
    {
        return match ($this->punch_type) {
            'am_in' => 'AM In',
            'am_out' => 'AM Out',
            'pm_in' => 'PM In',
            'pm_out' => 'PM Out',
            default => ucwords(str_replace('_', ' ', $this->punch_type)),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'badge-green',
            'rejected' => 'badge-red',
            default => 'badge-amber',
        };
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
