<?php

namespace App\Modules\WhatsAppCloud\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectWhatsAppChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'waba_id' => ['required', 'string', 'max:255'],
            'business_id' => ['required', 'string', 'max:255'],
            'phone_number_id' => ['required', 'string', 'max:255'],
            'access_token' => ['required', 'string'],
            'webhook_verify_token' => ['required', 'string', 'max:255'],
        ];
    }
}
