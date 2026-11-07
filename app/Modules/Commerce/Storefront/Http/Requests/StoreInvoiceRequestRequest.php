<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A signed-in customer asking the store for a custom invoice. */
class StoreInvoiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('store_customer') !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.note' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Tell the store what the invoice should cover.',
            'message.min' => 'Add a little more detail so the store knows what to invoice.',
            'items.max' => 'You can list up to 30 products in one request.',
        ];
    }
}
