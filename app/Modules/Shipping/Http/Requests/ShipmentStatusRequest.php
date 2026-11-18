<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(array_keys(Shipment::STATUSES))]];
    }
}
