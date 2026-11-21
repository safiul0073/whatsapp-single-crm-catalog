<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendOrderMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.orders.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel_account_id' => ['required', 'integer'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'channel_account_id.required' => __('Select an active channel before sending a message.'),
            'channel_account_id.integer' => __('Select a valid active channel.'),
            'subject.max' => __('The email subject may not exceed 255 characters.'),
            'body.required' => __('Enter a message to send to the customer.'),
            'body.max' => __('The message may not exceed 4096 characters.'),
        ];
    }
}
