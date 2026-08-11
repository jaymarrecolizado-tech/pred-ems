<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'log_date' => ['required', 'date', 'before_or_equal:today'],
            'punch_type' => ['required', 'in:am_in,am_out,pm_in,pm_out'],
            'requested_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
