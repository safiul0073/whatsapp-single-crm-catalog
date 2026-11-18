<?php

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductCostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'costs' => ['required', 'array', 'max:200'],
            'costs.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }
}
