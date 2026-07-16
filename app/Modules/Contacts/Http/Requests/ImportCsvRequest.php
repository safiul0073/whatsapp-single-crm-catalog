<?php

namespace App\Modules\Contacts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCsvRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('column_mapping'))) {
            $decoded = json_decode($this->input('column_mapping'), true);

            $this->merge([
                'column_mapping' => is_array($decoded) ? $decoded : [],
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'column_mapping' => ['nullable', 'array'],
            'column_mapping.*' => ['nullable', 'string'],
            'update_existing' => ['nullable', 'boolean'],
            'mark_optin' => ['nullable', 'boolean'],
            'sheet' => ['nullable', 'string'],
        ];
    }
}
