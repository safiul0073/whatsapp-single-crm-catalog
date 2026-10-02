<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace');
    }

    public function rules(): array
    {
        return ['customer_reference' => ['required', 'string', 'max:150']];
    }

    public function messages(): array
    {
        return ['customer_reference.required' => 'A customer reference is required.'];
    }
}
