<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date'],
            'type' => ['required', 'in:regular_holiday,special_nonworking,work_suspension'],
            'is_repeating' => ['nullable', 'boolean'],
        ];
    }
}
