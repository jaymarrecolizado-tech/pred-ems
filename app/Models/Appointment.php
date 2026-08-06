<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'employee_id', 'position_id', 'division_id', 'employment_type_id',
        'appointment_type', 'appointment_status',
        'salary_grade', 'step', 'monthly_salary',
        'effective_from', 'effective_to', 'remarks', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'monthly_salary' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function employmentType()
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function getAppointmentTypeLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->appointment_type));
    }
}
