<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace');
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'challenge','resend' => ['phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/D'], 'session_binding' => ['required', 'string', 'min:32', 'max:128'], 'consent' => ['accepted']],
            'verify' => ['code' => ['required', 'digits:6'], 'session_binding' => ['required', 'string', 'min:32', 'max:128'], 'referral_code' => ['nullable', 'string', 'max:32']],
            'register' => ['challenge_id' => ['required', 'uuid'], 'registration_grant' => ['required', 'string', 'size:64'], 'session_binding' => ['required', 'string', 'min:32', 'max:128'], 'name' => ['required', 'string', 'max:150'], 'email' => ['nullable', 'email', 'max:255'], 'referral_code' => ['nullable', 'string', 'max:32']],
            'passwordLogin' => ['phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/D'], 'password' => ['required', 'string', 'max:255']],
            'password' => ['password' => ['required', 'string', 'min:12', 'max:255', 'confirmed']],
            default => [],
        };
    }
}
