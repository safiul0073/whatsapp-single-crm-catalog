<?php

namespace App\Modules\Currencies\Http\Requests;

use App\Modules\Currencies\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currency = $this->route('currency');
        $currencyId = $currency instanceof Currency ? $currency->getKey() : $currency;

        return [
            'code' => ['required', 'string', 'max:10', 'alpha_dash', Rule::unique('currencies', 'code')->ignore($currencyId)],
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

        $currency = $this->route('currency');
        $currencyId = $currency instanceof Currency ? $currency->getKey() : $currency;
        session()->flash('open_modal', 'editCurrencyModal-'.$currencyId);

        return $url;
    }
}
