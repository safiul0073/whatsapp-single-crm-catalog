<?php

namespace App\Modules\AuthApi\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string'],
            'otp' => ['required', 'string', 'digits:6'],
        ];
    }
}
