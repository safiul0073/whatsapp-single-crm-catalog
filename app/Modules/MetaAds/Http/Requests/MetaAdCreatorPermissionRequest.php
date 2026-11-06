<?php

namespace App\Modules\MetaAds\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetaAdCreatorPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('meta-ads.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'instagram_account_id' => ['required', 'string', 'max:64'],
            'creator_username' => ['required', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'creator_username.regex' => __('Enter the creator\'s Instagram username, like @shop.fan.'),
        ];
    }
}
