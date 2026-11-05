<?php

namespace App\Modules\Chatbots\Http\Requests;

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Services\ChannelAiReplyService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChannelAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('chatbots.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user());
        $rules = ['channels' => ['required', 'array']];

        foreach (ChannelAiReplyService::PROVIDERS as $provider) {
            $rules["channels.{$provider}"] = ['sometimes', 'array'];
            $rules["channels.{$provider}.enabled"] = ['nullable', 'boolean'];
            $rules["channels.{$provider}.chatbot_id"] = [
                Rule::requiredIf(fn (): bool => $this->boolean("channels.{$provider}.enabled")),
                'nullable',
                'integer',
                Rule::exists(Chatbot::class, 'id')->where('workspace_id', $workspace?->id),
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'channels.*.chatbot_id.required' => __('Choose a chatbot for every channel you switch on.'),
        ];
    }
}
