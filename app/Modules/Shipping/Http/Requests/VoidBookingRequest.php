<?php

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }

    public function messages(): array
    {
        return ['reason.required' => __('Say why the booking should be cancelled.')];
    }
}
