<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public static function paymentRules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'currency' => ['required', 'string', 'size:3'],
            'method' => ['required', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:150'],
            'submission_reference' => ['required', 'uuid'],
            'tendered_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    public function rules(): array
    {
        return self::paymentRules();
    }

    public function messages(): array
    {
        return ['amount.gt' => 'The payment must be greater than zero.', 'submission_reference.uuid' => 'Refresh the form and try recording the payment again.'];
    }
}
