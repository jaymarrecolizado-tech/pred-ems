<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['nullable', 'boolean'],
            'revert_schedule_id' => ['nullable', 'exists:work_schedules,id'],
            'days' => ['required', 'array'],
            'days.*.work' => ['nullable', 'boolean'],
            'days.*.am_start' => ['nullable', 'date_format:H:i'],
            'days.*.am_end' => ['nullable', 'date_format:H:i'],
            'days.*.pm_start' => ['nullable', 'date_format:H:i'],
            'days.*.pm_end' => ['nullable', 'date_format:H:i'],
        ];
    }
}
