<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\ShippingPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:40', 'regex:/^(agent|account):\d+$/'],
            'shipping_invoice_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(ShippingPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg', 'extensions:pdf,png,jpg,jpeg'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.regex' => __('Choose the agent or courier account that was paid.'),
            'receipt.mimes' => __('Upload the receipt as a PDF or image.'),
            'receipt.extensions' => __('Upload the receipt as a PDF or image.'),
        ];
    }
}
