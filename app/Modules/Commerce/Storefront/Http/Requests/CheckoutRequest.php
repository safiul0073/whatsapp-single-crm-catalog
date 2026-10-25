<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_customer');
    }

    public function rules(): array
    {
        return ['address_id' => ['required', 'integer'], 'shipping_method_id' => ['nullable', 'integer'], 'payment_method_id' => [$this->route()->getActionMethod() === 'store' ? 'required' : 'nullable', 'string', 'max:60'], 'use_rewards' => ['sometimes', 'boolean']];
    }
}
