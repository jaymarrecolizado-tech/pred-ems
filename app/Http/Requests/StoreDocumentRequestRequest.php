<?php

namespace App\Http\Requests;

use App\Models\DocumentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(array_keys(DocumentRequest::TYPES))],
            'purpose' => ['required', 'string', 'max:2000'],
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }
}
