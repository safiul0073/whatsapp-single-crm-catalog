<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.storefront.settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'meta_pixel_id' => ['nullable', 'regex:/^[0-9]{8,20}$/D'],
            'tiktok_pixel_id' => ['nullable', 'regex:/^[A-Z0-9]{10,30}$/D'],
            'meta_capi_enabled' => ['required', 'boolean'],
            'meta_capi_token' => ['nullable', 'string', 'max:1000'],
            'clear_token' => ['nullable', 'boolean'],
            'meta_test_event_code' => ['nullable', 'regex:/^[A-Za-z0-9]{1,30}$/D'],
            'tiktok_events_enabled' => ['nullable', 'boolean'],
            'tiktok_events_token' => ['nullable', 'string', 'max:1000'],
            'clear_tiktok_token' => ['nullable', 'boolean'],
            'tiktok_test_event_code' => ['nullable', 'regex:/^[A-Za-z0-9_]{1,40}$/D'],
        ];
    }

    public function messages(): array
    {
        return [
            'meta_pixel_id.regex' => 'Enter the numeric Meta Pixel ID (8–20 digits).',
            'tiktok_pixel_id.regex' => 'Enter the TikTok Pixel ID using capital letters and digits only.',
            'tiktok_test_event_code.regex' => 'Enter the test event code exactly as shown in TikTok Events Manager.',
            'meta_test_event_code.regex' => 'Enter the test event code exactly as shown in Meta Events Manager.',
        ];
    }
}
