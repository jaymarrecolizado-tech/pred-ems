<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'honoraria' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'overtime_pay' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'other_income' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lwop' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'other_deductions' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }
}
