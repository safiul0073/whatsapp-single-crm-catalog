<?php

namespace App\Modules\PlansSubscriptions\Http\Requests;

use App\Modules\PaymentGateways\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class SubmitManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ['fields' => ['nullable', 'array']];

        foreach ($this->fieldDefinitions() as $index => $field) {
            $presence = ! empty($field['required']) ? 'required' : 'nullable';

            $rules["fields.{$index}"] = match ($field['type'] ?? 'text') {
                'number' => [$presence, 'numeric'],
                'textarea' => [$presence, 'string', 'max:2000'],
                'file' => [$presence, 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
                default => [$presence, 'string', 'max:255'],
            };
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->fieldDefinitions() as $index => $field) {
            $attributes["fields.{$index}"] = (string) ($field['label'] ?? __('Field'));
        }

        return $attributes;
    }

    /**
     * @return array<int, array{label?: string, type?: string, required?: bool}>
     */
    public function fieldDefinitions(): array
    {
        $payment = $this->route('payment');

        if (! $payment instanceof Payment) {
            return [];
        }

        return array_values(array_filter(
            (array) data_get($payment->metadata, 'user_fields_definition', []),
            fn (mixed $field): bool => is_array($field),
        ));
    }
}
