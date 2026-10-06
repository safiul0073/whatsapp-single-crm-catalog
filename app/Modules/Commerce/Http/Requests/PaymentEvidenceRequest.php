<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Models\StoreOrderSetting;
use Illuminate\Foundation\Http\FormRequest;

class PaymentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace');
    }

    public function rules(): array
    {
        $rules = [
            'payment_method_id' => ['required', 'string', 'max:60'],
            'transaction_id' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:2000'],
            'receipts' => ['required', 'array', 'min:1', 'max:5'],
            'receipts.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'fields' => ['nullable', 'array', 'max:10'],
            'fields.*' => ['nullable', 'string', 'max:500'],
        ];
        $workspace = $this->attributes->get('store_workspace');
        $method = $workspace ? collect(StoreOrderSetting::paymentMethods($workspace->id, true))->firstWhere('id', $this->input('payment_method_id')) : null;
        foreach ($method['fields'] ?? [] as $field) {
            $rules['fields.'.$field['name']] = [$field['required'] ? 'required' : 'nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function ($validator): void {
            $workspace = $this->attributes->get('store_workspace');
            if ($workspace && ! collect(StoreOrderSetting::paymentMethods($workspace->id, true))->contains('id', $this->input('payment_method_id'))) {
                $validator->errors()->add('payment_method_id', 'Choose an available payment method.');
            }
        }];
    }

    public function messages(): array
    {
        return ['receipts.required' => 'Upload at least one payment screenshot.', 'transaction_id.required' => 'Enter your transaction ID.'];
    }
}
