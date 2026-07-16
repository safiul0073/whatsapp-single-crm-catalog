<?php

namespace App\Modules\AuthApi\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SetupOtpTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'in:email,sms'],
        ];
    }
}
