<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'payroll_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
