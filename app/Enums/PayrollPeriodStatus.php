<?php

namespace App\Enums;

enum PayrollPeriodStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Paid = 'paid';
    case Voided = 'voided';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
