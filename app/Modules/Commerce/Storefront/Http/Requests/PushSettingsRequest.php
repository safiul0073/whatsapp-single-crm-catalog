<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PushSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'project_id' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'api_key' => ['nullable', 'string', 'max:100'],
            'messaging_sender_id' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'android_app_id' => ['nullable', 'string', 'max:120', 'regex:/^1:\d+:android:[0-9a-f]+$/'],
            'ios_app_id' => ['nullable', 'string', 'max:120', 'regex:/^1:\d+:ios:[0-9a-f]+$/'],
            'ios_bundle_id' => ['nullable', 'string', 'max:150'],
            'service_account' => ['nullable', 'string', 'max:20000'],
            'service_account_file' => ['nullable', 'file', 'max:20', 'mimetypes:application/json,text/plain'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.regex' => 'Use the Firebase project ID (lowercase letters, numbers and dashes), e.g. fashion-fiest-12ab3.',
            'messaging_sender_id.regex' => 'The sender ID is the number in Project settings → Cloud Messaging.',
            'android_app_id.regex' => 'The Android app ID looks like 1:1234567890:android:abc123…',
            'ios_app_id.regex' => 'The iOS app ID looks like 1:1234567890:ios:abc123…',
        ];
    }

    /**
     * Owner input with the uploaded key file read into `service_account`.
     *
     * @return array<string, mixed>
     */
    public function pushData(): array
    {
        $data = $this->safe()->except('service_account_file');
        $data['enabled'] = $this->boolean('enabled');
        if ($this->hasFile('service_account_file')) {
            $data['service_account'] = (string) $this->file('service_account_file')->get();
        }

        return $data;
    }
}
