<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkRemittedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_no' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
