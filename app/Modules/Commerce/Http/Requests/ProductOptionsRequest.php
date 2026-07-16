<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $options = collect($this->input('options', []))->map(function (array $option): array {
            $option['values'] = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($option['values_csv'] ?? ''))))));

            return $option;
        })->all();

        $this->merge(['options' => $options]);
    }

    public function rules(): array
    {
        return [
            'options' => ['required', 'array', 'min:1', 'max:5'],
            'options.*.name' => ['required', 'string', 'max:80', 'distinct'],
            'options.*.code' => ['required', 'alpha_dash', 'max:80', 'distinct'],
            'options.*.values' => ['required', 'array', 'min:1', 'max:30'],
            'options.*.values.*' => ['required', 'string', 'max:80'],
        ];
    }
}
