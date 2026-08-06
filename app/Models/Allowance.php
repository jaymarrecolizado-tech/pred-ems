<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Allowance extends Model
{
    protected $fillable = [
        'code', 'name', 'computation', 'amount', 'percentage',
        'is_taxable', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
