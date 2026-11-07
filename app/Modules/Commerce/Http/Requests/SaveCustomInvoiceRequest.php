<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Create or update a custom invoice; lines may point at a product or be free text. */
class SaveCustomInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.invoices.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function invoiceRules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:9999999999'];

        return [
            'customer_id' => ['nullable', 'integer'],
            'invoice_request_id' => ['nullable', 'integer'],
            'bill_to_name' => ['nullable', 'required_without:customer_id', 'string', 'max:255'],
            'bill_to_phone' => ['nullable', 'string', 'max:40'],
            'bill_to_email' => ['nullable', 'email', 'max:255'],
            'bill_to_address' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'required_without:items.*.product_id', 'string', 'max:250'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['nullable', 'required_without:items.*.product_id', 'numeric', 'min:0', 'max:9999999999'],
            'discount_amount' => $money,
            'tax_amount' => $money,
            'shipping_amount' => $money,
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'issue' => ['sometimes', 'boolean'],
        ];
    }

    public function rules(): array
    {
        return self::invoiceRules();
    }

    public function messages(): array
    {
        return [
            'bill_to_name.required_without' => 'Pick a customer or enter who the invoice is for.',
            'items.required' => 'Add at least one line to the invoice.',
            'items.*.name.required_without' => 'Custom lines need a description.',
            'items.*.unit_price.required_without' => 'Custom lines need a price.',
        ];
    }
}
