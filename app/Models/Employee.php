<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'employee_number', 'user_id',
        'first_name', 'middle_name', 'last_name', 'suffix',
        'birth_date', 'birth_place', 'gender', 'civil_status', 'citizenship',
        'blood_type', 'residential_address', 'contact_number', 'personal_email',
        'gov_email', 'gsis_no', 'philhealth_no', 'pagibig_no', 'tin_no', 'sss_no',
        'employment_type_id', 'division_id', 'position_id',
        'plantilla_item_no', 'bp_number', 'source_of_fund',
        'salary_grade', 'step', 'monthly_salary',
        'date_original_appointment', 'date_last_promotion',
        'status', 'profile_photo_path', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'date_original_appointment' => 'date',
            'date_last_promotion' => 'date',
            'monthly_salary' => 'decimal:2',
            'salary_grade' => 'integer',
            'step' => 'integer',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                          */
    /* ------------------------------------------------------------------ */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employmentType()
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class)->orderBy('effective_from');
    }

    public function allowances()
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    public function leaveCredits()
    {
        return $this->hasMany(LeaveCreditLedger::class);
    }

    public function educations()
    {
        return $this->hasMany(EmployeeEducation::class);
    }

    public function civilServiceEligibilities()
    {
        return $this->hasMany(EmployeeCivilServiceEligibility::class);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name ? mb_substr($this->middle_name, 0, 1) . '.' : null,
            $this->last_name,
            $this->suffix,
        ])));
    }

    public function getInitialsAttribute(): string
    {
        $initials = mb_substr($this->first_name, 0, 1) . mb_substr($this->last_name, 0, 1);

        return strtoupper($initials);
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    /**
     * Human-readable years/months in service, counted from the original
     * appointment date (falls back to '—' when unknown).
     */
    public function getYearsOfServiceAttribute(): string
    {
        $start = $this->date_original_appointment;
        if (! $start) {
            return '—';
        }

        $diff = $start->diff(now());
        if ($diff->y === 0 && $diff->m === 0) {
            return 'Less than a month';
        }

        $parts = [];
        if ($diff->y > 0) {
            $parts[] = $diff->y . ' yr' . ($diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m . ' mo' . ($diff->m > 1 ? 's' : '');
        }

        return implode(' ', $parts) ?: '—';
    }

    /**
     * 201-file completeness percentage — how many key profile fields are filled.
     */
    public function getProfileCompletenessAttribute(): int
    {
        $fields = [
            'first_name', 'last_name', 'birth_date', 'gender', 'civil_status',
            'contact_number', 'personal_email', 'gov_email', 'residential_address',
            'gsis_no', 'philhealth_no', 'pagibig_no', 'tin_no',
            'employment_type_id', 'position_id', 'salary_grade',
            'monthly_salary', 'date_original_appointment',
        ];

        $filled = collect($fields)->filter(fn ($field) => filled($this->{$field}))->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    public function getCurrentAppointmentAttribute(): ?Appointment
    {
        return $this->appointments->last();
    }

    /**
     * Leave balances per leave type, derived from the append-only ledger.
     *
     * @return \Illuminate\Support\Collection<LeaveType, float>
     */
    public function leaveBalances(): \Illuminate\Support\Collection
    {
        return LeaveType::query()
            ->orderBy('code')
            ->get()
            ->map(function (LeaveType $type) {
                $balance = (float) LeaveCreditLedger::query()
                    ->where('employee_id', $this->id)
                    ->where('leave_type_id', $type->id)
                    ->selectRaw('COALESCE(SUM(credit - debit), 0) as balance')
                    ->value('balance');

                return (object) [
                    'leave_type' => $type,
                    'balance' => $balance,
                ];
            })
            ->reject(fn ($row) => $row->balance == 0 && $row->leave_type->code !== 'VL' && $row->leave_type->code !== 'SL');
    }
}
