<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace');
    }

    public function rules(): array
    {
        return ['note' => ['required_without:receipt', 'nullable', 'string', 'max:2000'], 'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096']];
    }

    public function messages(): array
    {
        return ['note.required_without' => 'Add a payment reference or receipt.'];
    }
}
