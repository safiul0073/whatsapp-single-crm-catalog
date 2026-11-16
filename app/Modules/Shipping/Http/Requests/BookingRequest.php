<?php

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => ['required', 'string', 'max:80'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'status' => ['nullable', Rule::in(['booked', 'in_transit', 'delivered'])],
        ];
    }
}
