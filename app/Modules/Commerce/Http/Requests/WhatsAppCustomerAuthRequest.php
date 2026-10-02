<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WhatsAppCustomerAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace');
    }

    public function rules(): array
    {
        $common = ['session_binding' => ['required', 'string', 'size:64']];

        return $common + match ($this->route()->getActionMethod()) {
            'challenge' => ['phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'], 'consent' => ['required', 'accepted'], 'client_ip' => ['required', 'ip']],
            'verify' => ['code' => ['required', 'regex:/^\d{6}$/']],
            default => ['challenge_id' => ['required', 'uuid'], 'grant' => ['required', 'string', 'size:64'], 'registration_reference' => ['required', 'uuid'], 'customer_reference' => ['required', 'string', 'max:100'], 'name' => ['required', 'string', 'regex:/\S/', 'max:150'], 'email' => ['nullable', 'email', 'max:254'], 'new_customer' => ['required', 'boolean']],
        };
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter your number with its international country code.', 'code.regex' => 'Enter the six-digit verification code.', 'consent.accepted' => 'Agree to receive your verification code and account welcome message.'];
    }
}
