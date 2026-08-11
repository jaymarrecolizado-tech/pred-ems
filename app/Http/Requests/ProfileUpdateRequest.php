<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', 'in:Male,Female'],
            'civil_status' => ['nullable', 'string', 'max:20'],
            'citizenship' => ['nullable', 'string', 'max:50'],
            'blood_type' => ['nullable', 'string', 'max:5'],
            'residential_address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'gsis_no' => ['nullable', 'string', 'max:30'],
            'philhealth_no' => ['nullable', 'string', 'max:30'],
            'pagibig_no' => ['nullable', 'string', 'max:30'],
            'tin_no' => ['nullable', 'string', 'max:30'],
            'sss_no' => ['nullable', 'string', 'max:30'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
