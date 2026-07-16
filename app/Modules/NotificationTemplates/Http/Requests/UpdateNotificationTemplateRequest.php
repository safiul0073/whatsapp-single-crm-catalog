<?php

namespace App\Modules\NotificationTemplates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'email_subject' => 'nullable|string|max:255',
            'email_body' => 'nullable|string',
            'sms_body' => 'nullable|string|max:1600',
            'in_app_title' => 'nullable|string|max:255',
            'in_app_body' => 'nullable|string|max:1000',
            'push_title' => 'nullable|string|max:255',
            'push_body' => 'nullable|string|max:1000',
            'channels' => 'nullable|array',
            'channels.*' => 'string|in:email,sms,in_app,web_push,mobile_push',
            'is_active' => 'nullable|boolean',
        ];
    }
}
