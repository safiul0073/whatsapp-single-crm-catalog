<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChatChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $channels = $this->input('chat_channels');
        if (! is_array($channels)) {
            return;
        }

        if (isset($channels['whatsapp']['phone']) && is_string($channels['whatsapp']['phone'])) {
            $phone = preg_replace('/[\s+\-().]/', '', trim($channels['whatsapp']['phone']));
            if (str_starts_with($phone, '00')) {
                $phone = substr($phone, 2);
            }
            $channels['whatsapp']['phone'] = $phone;
        }

        if (isset($channels['telegram']['username']) && is_string($channels['telegram']['username'])) {
            $username = trim($channels['telegram']['username']);
            $username = (string) preg_replace('#^https?://t\.me/#i', '', $username);
            $channels['telegram']['username'] = ltrim($username, '@');
        }

        if (isset($channels['messenger']['page_id']) && is_string($channels['messenger']['page_id'])) {
            $pageId = trim($channels['messenger']['page_id']);
            $pageId = (string) preg_replace('#^https?://(www\.)?(m\.me|facebook\.com)/#i', '', $pageId);
            $channels['messenger']['page_id'] = trim($pageId, '/');
        }

        $this->merge(['chat_channels' => $channels]);
    }

    public function rules(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user())->id;
        $custom = $this->boolean('chat_channels.enabled') && $this->boolean('chat_channels.custom_chatbot.enabled');
        $source = $this->input('chat_channels.custom_chatbot.source');

        return [
            'chat_channels' => 'required|array:enabled,position,layout,tooltip,whatsapp,messenger,telegram,custom_chatbot',
            'chat_channels.enabled' => 'required|boolean',
            'chat_channels.position' => 'required|in:bottom-right,bottom-left',
            'chat_channels.layout' => 'required|in:stacked,expandable',
            'chat_channels.tooltip' => 'nullable|string|max:150',
            'chat_channels.whatsapp' => 'required|array:enabled,phone,message',
            'chat_channels.whatsapp.enabled' => 'required|boolean',
            'chat_channels.whatsapp.phone' => [Rule::requiredIf($this->boolean('chat_channels.enabled') && $this->boolean('chat_channels.whatsapp.enabled')), 'nullable', 'regex:/^[1-9][0-9]{6,14}$/D'],
            'chat_channels.whatsapp.message' => 'nullable|string|max:1000',
            'chat_channels.messenger' => 'required|array:enabled,page_id',
            'chat_channels.messenger.enabled' => 'required|boolean',
            'chat_channels.messenger.page_id' => [Rule::requiredIf($this->boolean('chat_channels.enabled') && $this->boolean('chat_channels.messenger.enabled')), 'nullable', 'regex:/^[a-zA-Z0-9.]{1,100}$/D'],
            'chat_channels.telegram' => 'required|array:enabled,username',
            'chat_channels.telegram.enabled' => 'required|boolean',
            'chat_channels.telegram.username' => [Rule::requiredIf($this->boolean('chat_channels.enabled') && $this->boolean('chat_channels.telegram.enabled')), 'nullable', 'regex:/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/D'],
            'chat_channels.custom_chatbot' => 'required|array:enabled,source,title,icon,widget_token,script_url,direct_url',
            'chat_channels.custom_chatbot.enabled' => 'required|boolean',
            'chat_channels.custom_chatbot.source' => 'required|in:marketing_chatbot,custom_script,direct_link',
            'chat_channels.custom_chatbot.title' => 'required|string|max:80',
            'chat_channels.custom_chatbot.icon' => 'required|in:bot,chat',
            'chat_channels.custom_chatbot.widget_token' => [Rule::excludeIf($source !== 'marketing_chatbot'), Rule::requiredIf($custom), 'nullable', 'string', 'max:80', Rule::when($custom, Rule::exists('chatbot_widgets', 'public_token')->where('workspace_id', $workspace)->where('is_active', true))],
            'chat_channels.custom_chatbot.script_url' => [Rule::excludeIf($source !== 'custom_script'), Rule::requiredIf($custom), 'nullable', 'url:https', 'max:2048'],
            'chat_channels.custom_chatbot.direct_url' => [Rule::excludeIf($source !== 'direct_link'), Rule::requiredIf($custom), 'nullable', 'url:https', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'chat_channels.whatsapp.phone.regex' => 'Enter a country code and phone number using 7–15 digits, without a plus sign.',
            'chat_channels.custom_chatbot.widget_token.exists' => 'Choose an active chatbot widget from this workspace.',
            'chat_channels.custom_chatbot.script_url.url' => 'Enter the HTTPS loader URL supplied by your chat provider.',
        ];
    }
}
