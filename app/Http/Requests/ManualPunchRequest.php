<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManualPunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'log_date' => ['required', 'date'],
            'punch_type' => ['required', 'in:am_in,am_out,pm_in,pm_out'],
            'time' => ['required', 'date_format:H:i'],
            'remarks' => ['required', 'string', 'max:500'],
        ];
    }
}
