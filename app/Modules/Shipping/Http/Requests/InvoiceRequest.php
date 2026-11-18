<?php

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) $this->input('currency')),
            'invoice_number' => trim((string) $this->input('invoice_number')),
        ]);
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:40', 'regex:/^(agent|account|carrier):[a-z0-9]+$/'],
            'invoice_number' => ['required', 'string', 'max:80'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'stated_total' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt,pdf', 'extensions:xlsx,xls,csv,pdf'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.regex' => __('Choose the agent or courier that sent this invoice.'),
            'file.mimes' => __('Upload the invoice as Excel, CSV or PDF.'),
            'file.extensions' => __('Upload the invoice as Excel, CSV or PDF.'),
        ];
    }
}
