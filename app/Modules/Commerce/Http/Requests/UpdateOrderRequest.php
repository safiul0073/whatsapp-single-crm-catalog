<?php

namespace App\Modules\Commerce\Http\Requests;

/**
 * Owners edit only an order's products; customer, address and shipping method always come from the order.
 */
class UpdateOrderRequest extends CreateUnifiedOrderRequest
{
    protected function prepareForValidation(): void {}

    public function authorize(): bool
    {
        return $this->user()?->can('commerce.orders.update') ?? false;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['draft'] = ['prohibited'];
        $rules['adjustments'] = ['prohibited'];
        $rules['groups.*.keep_group_id'] = ['nullable', 'integer', 'min:1'];
        foreach (array_keys($rules) as $field) {
            if (in_array($field, ['contact_id', 'shipping_method_id', 'customer_reference'], true) || str_starts_with($field, 'customer.') || str_starts_with($field, 'shipping_address.')) {
                $rules[$field] = ['exclude'];
            }
        }
        foreach (['groups.*.variant_id', 'groups.*.quantity', 'groups.*.color_id', 'groups.*.box_count'] as $field) {
            $rules[$field] = array_merge(['exclude_with:groups.*.keep_group_id'], $rules[$field]);
        }

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ['groups.required' => 'An order needs at least one product line.']);
    }
}
