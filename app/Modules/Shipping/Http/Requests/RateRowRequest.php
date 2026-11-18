<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Rules\CountryCode;
use Illuminate\Foundation\Http\FormRequest;

class RateRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country_code' => filled($this->input('country_code')) ? strtoupper((string) $this->input('country_code')) : null]);
    }

    public function rules(): array
    {
        return [
            'country_code' => ['nullable', 'string', 'size:2', new CountryCode],
            'zone' => ['nullable', 'string', 'max:40'],
            'min_weight_kg' => ['required', 'numeric', 'min:0', 'max:99999'],
            'max_weight_kg' => ['nullable', 'numeric', 'gte:min_weight_kg', 'max:99999'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'price_per_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'transit_days' => ['nullable', 'string', 'max:40'],
        ];
    }
}
