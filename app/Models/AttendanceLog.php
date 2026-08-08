<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    public const PUNCH_TYPES = ['am_in', 'am_out', 'pm_in', 'pm_out'];

    protected $fillable = [
        'employee_id', 'log_date', 'punch_type', 'punched_at',
        'latitude', 'longitude', 'checkpoint_id', 'source', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'punched_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function checkpoint()
    {
        return $this->belongsTo(AttendanceCheckpoint::class);
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

    public function getTimeAttribute(): string
    {
        return $this->punched_at->format('h:i A');
    }
}
