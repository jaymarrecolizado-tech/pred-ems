<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMonetizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'days' => ['required', 'numeric', 'min:0.5', 'max:30'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
