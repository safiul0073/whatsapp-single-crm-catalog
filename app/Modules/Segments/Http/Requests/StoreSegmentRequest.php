<?php

namespace App\Modules\Segments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(['dynamic', 'static'])],
            'rules' => ['nullable', 'array'],
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['integer'],
        ];
    }
}
