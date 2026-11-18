<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\ShippingCharge;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => ['required_without:reference', 'nullable', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:80'],
            'ship_date' => ['nullable', 'date'],
            'billed_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'base_amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'charges' => ['nullable', 'array'],
            'charges.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    public function messages(): array
    {
        return ['tracking_number.required_without' => __('Enter the tracking number or the shipment reference.')];
    }

    /**
     * @return array<string, mixed>
     */
    public function lineData(): array
    {
        $data = $this->validated();
        $charges = [];
        foreach ($data['charges'] ?? [] as $reason => $amount) {
            if (array_key_exists($reason, ShippingCharge::REASONS) && filled($amount)) {
                $charges[] = ['reason' => $reason, 'amount' => $amount];
            }
        }

        return ['charges' => $charges] + $data;
    }
}
