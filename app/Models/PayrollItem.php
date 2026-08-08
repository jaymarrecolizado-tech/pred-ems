<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Per-employee payroll computation for a period. The full calculation trace
 * is kept in computation_json so every payslip stays auditable years later.
 */
class PayrollItem extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'payroll_period_id', 'employee_id',
        'basic_salary', 'pera', 'honoraria', 'overtime_pay', 'other_income', 'income_json',
        'gsis_employee_share', 'philhealth_employee_share', 'pagibig_employee_share',
        'withholding_tax', 'lwop_deduction', 'other_deductions', 'deduction_json',
        'gross_amount', 'total_deductions', 'net_amount', 'computation_json', 'status',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'pera' => 'decimal:2',
            'honoraria' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'other_income' => 'decimal:2',
            'income_json' => 'array',
            'gsis_employee_share' => 'decimal:2',
            'philhealth_employee_share' => 'decimal:2',
            'pagibig_employee_share' => 'decimal:2',
            'withholding_tax' => 'decimal:2',
            'lwop_deduction' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'deduction_json' => 'array',
            'gross_amount' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'computation_json' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }

    /**
     * Sum this item's income lines (basic + pera + honoraria + overtime + other).
     */
    public function incomeTotal(): float
    {
        return round(
            (float) $this->basic_salary
            + (float) $this->pera
            + (float) $this->honoraria
            + (float) $this->overtime_pay
            + (float) $this->other_income,
            2
        );
    }

    /**
     * Sum this item's deduction lines (statutory + lwop + other).
     */
    public function deductionTotal(): float
    {
        return round(
            (float) $this->gsis_employee_share
            + (float) $this->philhealth_employee_share
            + (float) $this->pagibig_employee_share
            + (float) $this->withholding_tax
            + (float) $this->lwop_deduction
            + (float) $this->other_deductions,
            2
        );
    }
}
