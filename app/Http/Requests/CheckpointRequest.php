<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
