<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_type' => ['required', Rule::in(['original', 'promotion', 'transfer', 'demotion', 're_appointment', 'co_terminus', 'job_order', 'contract_of_service', 'gip', 'casual', 'temporary'])],
            'appointment_status' => ['required', Rule::in(['approved', 'pending', 'revoked'])],
            'position_id' => ['nullable', 'exists:positions,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'employment_type_id' => ['nullable', 'exists:employment_types,id'],
            'salary_grade' => ['nullable', 'integer', 'min:1', 'max:33'],
            'step' => ['nullable', 'integer', 'min:1', 'max:8'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
