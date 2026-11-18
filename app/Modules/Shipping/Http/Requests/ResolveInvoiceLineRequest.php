<?php

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveInvoiceLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['link', 'ignore'])],
            'shipment_id' => ['required_if:action,link', 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['shipment_id.required_if' => __('Choose the shipment this line belongs to.')];
    }
}
