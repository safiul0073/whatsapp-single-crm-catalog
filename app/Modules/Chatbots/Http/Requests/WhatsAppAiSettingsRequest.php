<?php

namespace App\Modules\Chatbots\Http\Requests;

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WhatsAppAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('chatbots.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user());

        return [
            'enabled' => ['nullable', 'boolean'],
            'chatbot_id' => [
                Rule::requiredIf(fn (): bool => $this->boolean('enabled')),
                'nullable',
                'integer',
                Rule::exists(Chatbot::class, 'id')->where('workspace_id', $workspace?->id),
            ],
        ];
    }
}
