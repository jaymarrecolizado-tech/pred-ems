<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveCreditLedger extends Model
{
    use HasFactory;

    protected $table = 'leave_credit_ledger';

    protected $fillable = [
        'employee_id', 'leave_type_id', 'transaction_date', 'movement',
        'credit', 'debit', 'balance_after', 'source_id', 'source_type',
        'remarks', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'credit' => 'decimal:2',
            'debit' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
