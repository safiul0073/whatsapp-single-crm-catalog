<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\ShippingDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShippingDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(ShippingDocument::TYPES))],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,gif', 'extensions:pdf,png,jpg,jpeg,gif'],
        ];
    }

    public function messages(): array
    {
        return ['file.mimes' => __('Upload a PDF or image file.'), 'file.extensions' => __('Upload a PDF or image file.')];
    }
}
