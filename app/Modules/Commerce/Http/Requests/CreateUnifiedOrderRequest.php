<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUnifiedOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace') || ($this->user()?->can('commerce.manage') ?? false);
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('shipping_address', []);
        $address['country'] = strtoupper((string) ($address['country'] ?? ''));
        $this->merge(['shipping_address' => $address]);
    }

    public function rules(): array
    {
        $rules = [
            'submission_reference' => ['required', 'uuid'], 'draft' => ['sometimes', 'boolean'],
            'contact_id' => $this->attributes->has('store_workspace') ? ['prohibited'] : ['nullable', 'integer'], 'customer_reference' => ['nullable', 'string', 'max:150'],
            'customer.name' => ['required_without:contact_id', 'nullable', 'string', 'max:150'],
            'customer.phone' => ['required_without:contact_id', 'nullable', 'string', 'max:30'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'shipping_address.name' => ['required', 'string', 'max:150'],
            'shipping_address.phone' => ['required', 'string', 'max:30'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.state' => ['nullable', 'string', 'max:120'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_address.country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'shipping_method_id' => ['nullable', 'integer'],
            'groups' => ['required', 'array', 'min:1', 'max:50'],
            'groups.*.product_id' => ['required', 'integer', 'min:1'],
            'groups.*.mode' => ['required', 'in:retail,wholesale'],
            'groups.*.variant_id' => ['required_if:groups.*.mode,retail', 'integer', 'min:1'],
            'groups.*.quantity' => ['required_if:groups.*.mode,retail', 'integer', 'min:1', 'max:10000'],
            'groups.*.color_id' => ['required_if:groups.*.mode,wholesale', 'integer', 'min:1'],
            'groups.*.box_count' => ['required_if:groups.*.mode,wholesale', 'integer', 'min:1', 'max:100'],
            'groups.*.size_quantities' => ['prohibited'],
            'subtotal' => ['prohibited'], 'total' => ['prohibited'], 'groups.*.price' => ['prohibited'], 'groups.*.unit_price' => ['prohibited'], 'groups.*.line_total' => ['prohibited'],
            'groups.*.ratio' => ['prohibited'], 'groups.*.pack_sizes' => ['prohibited'],
            'adjustments' => ['sometimes', 'array', 'max:10'],
            'adjustments.*.type' => ['required', 'in:coupon,reward'],
            'adjustments.*.amount' => ['required', 'numeric', 'min:0'],
            'adjustments.*.currency' => ['required', 'string', 'size:3'],
            'adjustments.*.reference' => ['required', 'string', 'max:100'],
        ];
        if ($this->is('api/commerce/store/orders/preview')) {
            foreach (['customer.name', 'customer.phone', 'shipping_address.name', 'shipping_address.phone', 'shipping_address.line1', 'shipping_address.city'] as $field) {
                $rules[$field] = array_values(array_unique(array_merge(['nullable'], array_filter($rules[$field], fn ($rule) => ! str_starts_with($rule, 'required')))));
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['groups.*.ratio.prohibited' => 'Box ratios are configured by the owner.', 'groups.*.box_count.min' => 'Choose at least one whole box.', 'groups.required' => 'Add a retail product or wholesale box.'];
    }
}
