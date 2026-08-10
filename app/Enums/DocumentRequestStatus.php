<?php

namespace App\Enums;

enum DocumentRequestStatus: string
{
    case Pending = 'pending';
    case Issued = 'issued';
    case Rejected = 'rejected';
    case Canceled = 'canceled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
