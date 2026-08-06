<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCivilServiceEligibility extends Model
{
    protected $table = 'employee_civil_service_eligibilities';

    protected $fillable = [
        'employee_id', 'eligibility', 'rating', 'date_examined',
        'place_examined', 'license_number', 'validity_date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
