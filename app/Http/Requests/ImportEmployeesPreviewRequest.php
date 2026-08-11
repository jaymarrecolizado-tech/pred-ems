<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportEmployeesPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,xls,xlsx,txt'],
            'mode' => ['required', 'in:create,update,upsert'],
        ];
    }
}
