<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class QuoteOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    /**
     * The customer's delivery address is locked once complete; staff can only fill it in for
     * orders that arrived without one (e.g. WhatsApp catalog orders).
     */
    protected function prepareForValidation(): void
    {
        $order = $this->route('order');
        if ($order instanceof Order && $order->hasCompleteShippingAddress()) {
            $this->merge(['shipping_address' => $order->shipping_address]);

            return;
        }

        $this->merge(['shipping_address' => [
            'name' => $this->input('shipping_name'),
            'phone' => $this->input('shipping_phone'),
            'line1' => $this->input('shipping_line1'),
            'line2' => $this->input('shipping_line2'),
            'city' => $this->input('shipping_city'),
            'state' => $this->input('shipping_state'),
            'postal_code' => $this->input('shipping_postal_code'),
            'country' => $this->input('shipping_country'),
        ]]);
    }

    public function rules(): array
    {
        return [
            'shipping_address.name' => ['required', 'string', 'max:150'],
            'shipping_address.phone' => ['required', 'string', 'max:30'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.state' => ['nullable', 'string', 'max:120'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_address.country' => ['required', 'string', 'size:2'],
            'shipping_amount' => ['required', 'numeric', 'min:0'],
            'delivery_method' => ['nullable', 'string', 'max:120'],
            'delivery_notes' => ['nullable', 'string', 'max:2000'],
            'duties_disclosure' => ['nullable', 'string', 'max:2000'],
            'payment_url' => ['nullable', 'url:https', 'max:2048'],
        ];
    }
}
