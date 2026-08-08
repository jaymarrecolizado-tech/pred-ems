<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = [
        'code', 'name', 'accrual_per_month', 'annual_max_credit',
        'is_cumulative', 'is_commutable', 'requires_approval', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'accrual_per_month' => 'decimal:2',
            'annual_max_credit' => 'decimal:2',
            'is_cumulative' => 'boolean',
            'is_commutable' => 'boolean',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function applications()
    {
        return $this->hasMany(LeaveApplication::class);
    }
}
