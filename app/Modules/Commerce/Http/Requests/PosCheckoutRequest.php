<?php

namespace App\Modules\Commerce\Http\Requests;

class PosCheckoutRequest extends CreateUnifiedOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('fulfillment_type') === 'delivery') {
            parent::prepareForValidation();
        }

        $groups = is_array($this->input('groups')) ? $this->input('groups') : [];
        foreach ($groups as $index => $group) {
            if (($group['mode'] ?? null) === 'wholesale') {
                $groups[$index]['box_count'] = 1;
            }
        }
        $this->merge(['groups' => $groups]);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['groups.*.size_quantities']);
        $rules['groups.*.size_quantities'] = ['required_if:groups.*.mode,wholesale', 'prohibited_if:groups.*.mode,retail', 'array', 'min:1', 'max:30'];
        $rules['groups.*.size_quantities.*'] = ['required', 'integer', 'min:1', 'max:10000'];
        $rules['groups.*.box_count'] = ['required_if:groups.*.mode,wholesale', 'in:1'];
        $rules['fulfillment_type'] = ['required', 'in:pickup,delivery'];
        $rules['walk_in'] = ['required', 'boolean'];
        $rules['handover'] = ['sometimes', 'boolean'];
        $rules['draft'] = ['prohibited'];
        $rules['adjustments'] = ['prohibited'];
        $rules['customer_reference'] = ['prohibited'];
        $rules['source'] = ['prohibited'];
        $rules['subtotal'] = ['prohibited'];
        $rules['payment'] = ['sometimes', 'array:amount,currency,method,reference,submission_reference,tendered_amount'];
        foreach (RecordOrderPaymentRequest::paymentRules() as $field => $validation) {
            $rules['payment.'.$field] = array_map(fn (string $rule): string => $rule === 'required' ? 'required_with:payment' : $rule, $validation);
        }
        if ($this->boolean('walk_in')) {
            $rules['contact_id'] = ['prohibited'];
            foreach (['name', 'phone', 'email'] as $field) {
                $rules['customer.'.$field] = ['prohibited'];
            }
        }
        if ($this->input('fulfillment_type') === 'pickup') {
            $rules['shipping_address'] = ['prohibited'];
            $rules['shipping_method_id'] = ['prohibited'];
            foreach (array_keys($rules) as $field) {
                if (str_starts_with($field, 'shipping_address.')) {
                    unset($rules[$field]);
                }
            }
        }
        if ($this->routeIs('user.commerce.pos.preview')) {
            foreach (array_keys($rules) as $field) {
                if (str_starts_with($field, 'customer.') || in_array($field, ['shipping_address.name', 'shipping_address.phone', 'shipping_address.line1', 'shipping_address.city'])) {
                    $rules[$field] = array_values(array_filter($rules[$field], fn (string $rule): bool => ! str_starts_with($rule, 'required')));
                }
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ['fulfillment_type.required' => 'Choose pickup or delivery.', 'customer.name.required_without' => 'Enter a customer name for credit or delivery orders.', 'customer.phone.required_without' => 'Enter a customer phone number.', 'payment.amount.required_with' => 'Enter the payment amount.']);
    }
}
