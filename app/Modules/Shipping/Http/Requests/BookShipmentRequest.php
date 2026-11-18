<?php

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['confirm' => ['accepted']];
    }

    public function messages(): array
    {
        return ['confirm.accepted' => __('Tick the confirmation box: booking creates a real, billable shipment with the courier.')];
    }
}
