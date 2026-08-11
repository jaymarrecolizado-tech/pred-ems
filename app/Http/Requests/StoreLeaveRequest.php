<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'reason' => ['required', 'string', 'max:255'],
            'contact_during_leave' => ['nullable', 'string', 'max:100'],
            'commutation_requested' => ['nullable', 'boolean'],
        ];
    }
}
