<?php

namespace App\Enums;

enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Approved => 'badge-green',
            self::Rejected => 'badge-red',
            self::Cancelled => 'badge-gray',
            self::Pending => 'badge-amber',
        };
    }
}
