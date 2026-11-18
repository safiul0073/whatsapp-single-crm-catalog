<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingCharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billed_amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'billed_currency' => ['required', 'string', 'size:3', 'alpha'],
            'billed_exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'payment_status' => ['required', Rule::in(array_keys(Shipment::PAYMENT_STATUSES))],
            'charges' => ['nullable', 'array'],
            'charges.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function billingData(): array
    {
        $data = $this->validated();
        $charges = [];
        foreach ($data['charges'] ?? [] as $reason => $amount) {
            if (array_key_exists($reason, ShippingCharge::REASONS)) {
                $charges[] = ['reason' => $reason, 'amount' => $amount ?: 0];
            }
        }

        return ['charges' => $charges] + $data;
    }
}
