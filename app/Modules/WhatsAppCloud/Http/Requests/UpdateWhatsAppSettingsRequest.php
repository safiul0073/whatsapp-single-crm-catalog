<?php

namespace App\Modules\WhatsAppCloud\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings.whatsapp_graph_api_version' => ['required', 'string', 'max:20'],
            'settings.whatsapp_meta_app_id' => ['nullable', 'string', 'max:255'],
            'settings.whatsapp_meta_app_secret' => ['nullable', 'string', 'max:255'],
            'settings.whatsapp_default_verify_token' => ['nullable', 'string', 'max:255'],
            'settings.whatsapp_webhook_base_url' => ['nullable', 'url', 'max:2048'],
            'settings.whatsapp_auto_sync_templates' => ['nullable', 'boolean'],
            'settings.whatsapp_auto_sync_phone_numbers' => ['nullable', 'boolean'],
            'settings.whatsapp_embedded_signup_enabled' => ['nullable', 'boolean'],
            'settings.whatsapp_embedded_signup_config_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
