<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return ['whatsapp_notifications' => ['sometimes', 'boolean'], 'whatsapp_channel_id' => ['nullable', 'integer'], 'whatsapp_template_id' => ['nullable', 'integer'], 'currency' => ['required', 'in:USD,BDT,EUR,GBP,CAD,AUD,JPY,KWD,BHD,OMR,CHF,SAR,AED,INR,SGD,NZD,CNY'], 'reservation_hours' => ['required', 'integer', 'min:1', 'max:168'], 'payment_instructions' => ['nullable', 'string', 'max:4000']];
    }

    public function messages(): array
    {
        return ['currency.in' => 'Choose a supported store currency.'];
    }
}
