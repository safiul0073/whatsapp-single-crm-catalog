<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use App\Modules\Commerce\Http\Requests\UpdateOrderRequest;

/**
 * The customer's one-time edit: the same product-line rules as an owner edit. Customer, address,
 * shipping, coupons and the order reference always stay as they are.
 */
class CustomerOrderEditRequest extends UpdateOrderRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_customer');
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['submission_reference'] = ['exclude'];

        return $rules;
    }
}
