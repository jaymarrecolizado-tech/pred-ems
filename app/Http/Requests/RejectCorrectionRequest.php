<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'denial_reason' => ['required', 'string', 'max:500'],
        ];
    }
}
