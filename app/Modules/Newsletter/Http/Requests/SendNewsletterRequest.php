<?php

namespace App\Modules\Newsletter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['nullable', 'integer', 'exists:notification_templates,id'],
            'template_variables' => ['nullable', 'array'],
            'template_variables.*' => ['nullable', 'string', 'max:5000'],
            'title' => ['required_without:template_id', 'nullable', 'string', 'max:255'],
            'message' => ['required_without:template_id', 'nullable', 'string', 'max:10000'],
            'recipient_type' => ['required', 'string', Rule::in(['active', 'all', 'single'])],
            'subscriber_id' => ['nullable', 'integer', 'required_if:recipient_type,single', 'exists:subscribers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required_without' => __('A subject is required when no template is selected.'),
            'message.required_without' => __('A message is required when no template is selected.'),
            'subscriber_id.required_if' => __('Please select a subscriber when sending to a single recipient.'),
        ];
    }
}
