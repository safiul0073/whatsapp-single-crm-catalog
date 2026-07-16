<?php

namespace App\Modules\NotificationTemplates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::in(['email', 'sms'])],
            'template_id' => ['nullable', 'integer', 'exists:notification_templates,id'],
            'template_variables' => ['nullable', 'array'],
            'template_variables.*' => ['nullable', 'string', 'max:5000'],
            'title' => ['required_without:template_id', 'nullable', 'string', 'max:255'],
            'message' => ['required_without:template_id', 'nullable', 'string', 'max:10000'],
            'recipient_type' => ['required', 'string', Rule::in(['all_admins', 'all_users', 'role'])],
            'role_id' => ['nullable', 'integer', 'required_if:recipient_type,role', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required_without' => __('A title is required when no template is selected.'),
            'message.required_without' => __('A message is required when no template is selected.'),
            'role_id.required_if' => __('Please select a role when sending to a specific role.'),
        ];
    }
}
