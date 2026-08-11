<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uniqueRule = Rule::unique('employees', 'employee_number')
            ->ignore($this->route('employee')?->id);

        return [
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'employee_number' => ['nullable', 'string', 'max:30', $uniqueRule],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'maiden_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:Male,Female'],
            'civil_status' => ['nullable', 'string', 'max:20'],
            'citizenship' => ['nullable', 'string', 'max:50'],
            'blood_type' => ['nullable', 'string', 'max:5'],
            'residential_address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'gov_email' => ['nullable', 'email', 'max:150'],
            'gsis_no' => ['nullable', 'string', 'max:30'],
            'philhealth_no' => ['nullable', 'string', 'max:30'],
            'pagibig_no' => ['nullable', 'string', 'max:30'],
            'tin_no' => ['nullable', 'string', 'max:30'],
            'sss_no' => ['nullable', 'string', 'max:30'],
            'employment_type_id' => ['required', 'exists:employment_types,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'plantilla_item_no' => ['nullable', 'string', 'max:100'],
            'bp_number' => ['nullable', 'string', 'max:50'],
            'source_of_fund' => ['nullable', 'string', 'max:80'],
            'salary_grade' => ['nullable', 'integer', 'min:1', 'max:33'],
            'step' => ['nullable', 'integer', 'min:1', 'max:8'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'date_original_appointment' => ['nullable', 'date'],
            'date_last_promotion' => ['nullable', 'date'],
            'status' => ['required', 'in:active,on_leave,separated,resigned,retired'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
