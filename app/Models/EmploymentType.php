<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentType extends Model
{
    protected $fillable = [
        'code', 'name', 'has_leave_credits', 'has_gsis', 'has_philhealth',
        'has_pagibig', 'has_withholding_tax', 'requires_20pct_premium',
        'sort_order', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_leave_credits' => 'boolean',
            'has_gsis' => 'boolean',
            'has_philhealth' => 'boolean',
            'has_pagibig' => 'boolean',
            'has_withholding_tax' => 'boolean',
            'requires_20pct_premium' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
