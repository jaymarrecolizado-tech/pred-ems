<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'mode' => ['required', 'in:create,update,upsert'],
        ];
    }
}
