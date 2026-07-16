<?php

namespace App\Modules\Currencies\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:10|unique:currencies,code|alpha_dash',
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:32',
            'exchange_rate' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', false),
            'code' => strtoupper($this->input('code')),
            'symbol' => currency_normalize_symbol((string) $this->input('symbol')),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        $url = parent::getRedirectUrl();
        session()->flash('open_modal', 'addCurrencyModal');

        return $url;
    }
}
