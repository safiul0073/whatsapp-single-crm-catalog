<?php

namespace App\Modules\MetaSocial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaAppSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meta_app_id' => ['nullable', 'string', 'max:255'],
            'meta_app_secret' => ['nullable', 'string', 'max:255'],
            'meta_graph_api_version' => ['required', 'string', 'regex:/^v\d+\.\d+$/'],
            'meta_default_verify_token' => ['nullable', 'string', 'max:255'],
            'meta_webhook_base_url' => ['nullable', 'url', 'max:2048'],
            'meta_business_config_id' => ['nullable', 'string', 'max:255'],
            'meta_ads_config_id' => ['nullable', 'string', 'max:255'],
            'meta_threads_app_id' => ['nullable', 'string', 'max:255'],
            'meta_threads_app_secret' => ['nullable', 'string', 'max:255'],
            'whatsapp_embedded_signup_config_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_embedded_signup_enabled' => ['nullable', 'boolean'],
            'whatsapp_auto_sync_templates' => ['nullable', 'boolean'],
            'whatsapp_auto_sync_phone_numbers' => ['nullable', 'boolean'],
            'meta_social_messenger_config_id' => ['nullable', 'string', 'max:255'],
            'meta_social_instagram_config_id' => ['nullable', 'string', 'max:255'],
            'meta_social_embedded_signup_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'meta_graph_api_version.regex' => __('Use a Graph API version like v24.0.'),
        ];
    }

    /**
     * @return array{meta_app: array<string, mixed>, whatsapp: array<string, mixed>, social: array<string, mixed>}
     */
    public function grouped(): array
    {
        $validated = $this->validated();
        $only = fn (array $keys): array => array_map(fn (string $key): string => (string) ($validated[$key] ?? ''), array_combine($keys, $keys));

        return [
            'meta_app' => $only(['meta_app_id', 'meta_app_secret', 'meta_graph_api_version', 'meta_default_verify_token', 'meta_webhook_base_url', 'meta_business_config_id', 'meta_ads_config_id', 'meta_threads_app_id', 'meta_threads_app_secret']),
            'whatsapp' => $only(['whatsapp_embedded_signup_config_id']) + [
                'whatsapp_embedded_signup_enabled' => $this->boolean('whatsapp_embedded_signup_enabled'),
                'whatsapp_auto_sync_templates' => $this->boolean('whatsapp_auto_sync_templates'),
                'whatsapp_auto_sync_phone_numbers' => $this->boolean('whatsapp_auto_sync_phone_numbers'),
            ],
            'social' => $only(['meta_social_messenger_config_id', 'meta_social_instagram_config_id']) + [
                'meta_social_embedded_signup_enabled' => $this->boolean('meta_social_embedded_signup_enabled'),
            ],
        ];
    }
}
