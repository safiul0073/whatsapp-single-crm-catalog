<?php

namespace App\Modules\Chatbots\Http\Requests;

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatbotWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user());

        return [
            'name' => ['required', 'string', 'max:120'],
            'chatbot_id' => [
                'required',
                'integer',
                Rule::exists(Chatbot::class, 'id')->where('workspace_id', $workspace->id),
            ],
            'greeting' => ['nullable', 'string', 'max:255'],
            'allowed_domains' => ['nullable', 'string', 'max:2000'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['required', Rule::in(['left', 'right'])],
            'launcher_label' => ['required', 'string', 'max:40'],
            'automated_reply_enabled' => ['nullable', 'boolean'],
            'lead_fields' => ['nullable', 'array'],
            'lead_fields.*' => ['string', Rule::in(['name', 'email', 'phone'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
