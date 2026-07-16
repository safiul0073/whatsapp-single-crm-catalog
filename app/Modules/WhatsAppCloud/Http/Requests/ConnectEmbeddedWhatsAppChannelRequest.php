<?php

namespace App\Modules\WhatsAppCloud\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectEmbeddedWhatsAppChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'waba_id' => ['nullable', 'string', 'max:255'],
            'phone_number_id' => ['nullable', 'string', 'max:255'],
            'business_id' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
