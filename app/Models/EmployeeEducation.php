<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeEducation extends Model
{
    protected $table = 'employee_educations';

    protected $fillable = [
        'employee_id', 'level', 'school_name', 'course', 'year_graduated', 'honors',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
