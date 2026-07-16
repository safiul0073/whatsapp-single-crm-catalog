<?php

namespace App\Modules\AuthApi\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class MobileSocialLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
