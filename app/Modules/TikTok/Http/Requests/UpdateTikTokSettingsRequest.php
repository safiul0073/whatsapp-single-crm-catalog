<?php

namespace App\Modules\TikTok\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTikTokSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiktok.settings.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'tiktok_login_enabled' => ['nullable', 'boolean'],
            'tiktok_client_key' => [Rule::requiredIf($this->boolean('tiktok_login_enabled')), 'nullable', 'string', 'max:255'],
            'tiktok_client_secret' => ['nullable', 'string', 'max:255'],
            'tiktok_scopes' => ['required', 'string', 'max:255', 'regex:/^[a-z]+(\.[a-z_]+)+(,[a-z]+(\.[a-z_]+)+)*$/D'],
            'tiktok_shop_enabled' => ['nullable', 'boolean'],
            'tiktok_shop_app_key' => ['nullable', 'string', 'max:255'],
            'tiktok_shop_app_secret' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Unchecked boxes are absent from the request, so booleans are read explicitly.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return array_merge($this->validated(), [
            'tiktok_login_enabled' => $this->boolean('tiktok_login_enabled'),
            'tiktok_shop_enabled' => $this->boolean('tiktok_shop_enabled'),
        ]);
    }

    public function messages(): array
    {
        return [
            'tiktok_scopes.regex' => 'Enter comma-separated scopes such as user.info.basic,video.upload.',
        ];
    }
}
