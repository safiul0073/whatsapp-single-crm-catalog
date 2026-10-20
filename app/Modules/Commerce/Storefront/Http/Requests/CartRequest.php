<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace');
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'add' => ['variant_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'min:1', 'max:10000']],
            'update' => ['quantity' => ['required', 'integer', 'min:1', 'max:10000']],
            'coupon' => ['code' => ['required', 'string', 'max:100']],default => []
        };
    }
}
