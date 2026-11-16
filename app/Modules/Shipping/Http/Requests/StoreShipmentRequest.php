<?php

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['order_id' => ['required', 'integer']];
    }
}
