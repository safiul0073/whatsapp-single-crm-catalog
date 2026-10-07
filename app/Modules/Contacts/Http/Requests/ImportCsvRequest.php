<?php

namespace App\Modules\Contacts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use libphonenumber\PhoneNumberUtil;

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

        if (is_string($this->input('default_country'))) {
            $this->merge(['default_country' => strtoupper($this->input('default_country'))]);
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
            'default_country' => ['required', 'string', 'size:2', Rule::in(PhoneNumberUtil::getInstance()->getSupportedRegions())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_country.required' => 'Choose the default country code for numbers without a + prefix.',
            'default_country.in' => 'Choose a valid default country.',
            'default_country.size' => 'Choose a valid default country.',
        ];
    }
}
