<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\ShippingAgent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active'), 'currency' => strtoupper((string) $this->input('currency'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'carrier' => ['nullable', Rule::in(array_keys(CarrierAccount::CARRIERS))],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'fuel_surcharge_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'other_fee' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'other_fee_label' => ['nullable', 'string', 'max:80'],
            'dim_divisor' => ['required', 'integer', 'min:1000', 'max:10000'],
            'weight_rounding_step' => ['required', 'numeric', 'gt:0', 'max:5'],
            'payment_status' => ['required', Rule::in(ShippingAgent::PAYMENT_STATUSES)],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
