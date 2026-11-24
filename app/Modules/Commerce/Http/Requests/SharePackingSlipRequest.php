<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SharePackingSlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.orders.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'order_ids' => [$this->route('order') ? 'prohibited' : 'required', 'array', 'min:1', 'max:50'],
            'order_ids.*' => ['required', 'integer', 'distinct'],
            'recipients' => ['nullable', 'array', 'max:20', 'required_without:extra_phone'],
            'recipients.*' => ['required', 'string', 'max:32'],
            'extra_phone' => ['nullable', 'string', 'max:32', 'regex:/^\+[0-9][0-9 ()\-]{6,30}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipients.required_without' => __('Pick at least one recipient or enter a WhatsApp number.'),
            'extra_phone.regex' => __('Enter an international WhatsApp number with +, for example +8801711223344.'),
            'order_ids.required' => __('Select at least one order to share.'),
            'order_ids.max' => __('You can share up to 50 packing slips at a time.'),
        ];
    }
}
