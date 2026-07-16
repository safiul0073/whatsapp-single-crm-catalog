<?php

namespace App\Modules\MetaSocial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectMetaSocialChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:1000'],
            'access_token' => ['nullable', 'string', 'max:5000'],
            'provider_account_id' => ['nullable', 'string', 'max:255'],
            'page_id' => ['nullable', 'string', 'max:255'],
            'page_name' => ['nullable', 'string', 'max:255'],
            'instagram_account_id' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
