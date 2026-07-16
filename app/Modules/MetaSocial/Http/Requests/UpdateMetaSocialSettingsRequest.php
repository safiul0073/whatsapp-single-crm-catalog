<?php

namespace App\Modules\MetaSocial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaSocialSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meta_social_graph_api_version' => ['required', 'string', 'max:20'],
            'meta_social_app_id' => ['nullable', 'string', 'max:255'],
            'meta_social_app_secret' => ['nullable', 'string', 'max:255'],
            'meta_social_default_verify_token' => ['nullable', 'string', 'max:255'],
            'meta_social_webhook_base_url' => ['nullable', 'url', 'max:255'],
            'meta_social_embedded_signup_enabled' => ['nullable', 'boolean'],
            'meta_social_messenger_config_id' => ['nullable', 'string', 'max:255'],
            'meta_social_instagram_config_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function settings(): array
    {
        return array_merge($this->validated(), [
            'meta_social_embedded_signup_enabled' => $this->boolean('meta_social_embedded_signup_enabled'),
        ]);
    }
}
