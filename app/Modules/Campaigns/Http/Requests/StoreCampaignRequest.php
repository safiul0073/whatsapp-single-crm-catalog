<?php

namespace App\Modules\Campaigns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'channel_account_id' => ['required', 'integer', 'exists:channel_accounts,id'],
            'message_type' => ['required', 'in:custom,template,automation'],
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'automation_id' => ['nullable', 'integer', 'exists:automations,id'],
            'type' => ['nullable', 'in:broadcast,follow_up,automation'],
            'audience_type' => ['required', 'in:contacts,groups,tags,imported'],
            'audience_ids' => ['nullable', 'array'],
            'audience_ids.*' => ['integer'],
            'message_subject' => ['nullable', 'string', 'max:255'],
            'message_body' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'settings.crm_create_lead_on_reply' => ['nullable', 'boolean'],
            'schedule' => ['required', 'in:now,later,draft'],
            'send_date' => ['nullable', 'date'],
            'send_time' => ['nullable', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone'],
            'throttle' => ['nullable', 'integer', 'min:0'],
            'use_ai_copy' => ['nullable', 'boolean'],
        ];
    }
}
