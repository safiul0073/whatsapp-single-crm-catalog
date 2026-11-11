<?php

namespace App\Modules\PlansSubscriptions\Http\Requests;

use App\Modules\PaymentGateways\Services\PaymentGatewayManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'gateway' => ['required', 'string', Rule::in(app(PaymentGatewayManager::class)->getEnabledGatewayNames())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gateway.required' => __('Please choose a payment method.'),
            'gateway.in' => __('The selected payment method is not available.'),
        ];
    }
}
