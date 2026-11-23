<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.orders.settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('payment_methods') && blank($this->input('payment_methods'))) {
            $this->merge(['payment_methods' => []]);
        }
    }

    public function rules(): array
    {
        return [
            'payment_methods' => ['required', 'array', 'max:30'],
            'payment_methods.*.id' => ['required', 'string', 'regex:/^[a-z0-9-]+$/', 'max:60', 'distinct'],
            'payment_icons' => ['sometimes', 'array', 'max:30'],
            'payment_icons.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'payment_methods.*.icon_path' => ['prohibited'],
            'payment_methods.*.icon_url' => ['prohibited'],
            'payment_methods.*.remove_icon' => ['sometimes', 'boolean'],
            'payment_qr_codes' => ['sometimes', 'array', 'max:30'],
            'payment_qr_codes.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'payment_methods.*.qr_path' => ['prohibited'],
            'payment_methods.*.qr_url' => ['prohibited'],
            'payment_methods.*.remove_qr' => ['sometimes', 'boolean'],
            'payment_methods.*.name' => ['required', 'string', 'max:100'],
            'payment_methods.*.recipient_details' => ['nullable', 'string', 'max:2000'],
            'payment_methods.*.instructions' => ['nullable', 'string', 'max:4000'],
            'payment_methods.*.video_url' => ['nullable', 'url:https', 'max:500', 'regex:#^https://(www\.|m\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com|player\.vimeo\.com)/#i'],
            'payment_methods.*.help_url' => ['nullable', 'url:https', 'max:500'],
            'payment_methods.*.active' => ['required', 'boolean'],
            'payment_methods.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'payment_methods.*.fields' => ['sometimes', 'array', 'max:10'],
            'payment_methods.*.fields.*.name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/', 'max:60'],
            'payment_methods.*.fields.*.label' => ['required', 'string', 'max:100'],
            'payment_methods.*.fields.*.required' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            foreach ((array) $this->input('payment_methods', []) as $index => $method) {
                if (! is_array($method)) {
                    continue;
                }
                if (! empty($method['active']) && blank($method['recipient_details'] ?? null)) {
                    $validator->errors()->add("payment_methods.$index.recipient_details", 'Enter receiving details before enabling this method.');
                }
                $names = array_column((array) ($method['fields'] ?? []), 'name');
                if (count($names) !== count(array_unique($names))) {
                    $validator->errors()->add("payment_methods.$index.fields", 'Additional field names must be unique per method.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return ['payment_qr_codes.*.image' => 'Upload a JPEG, PNG, or WebP QR code image.', 'payment_qr_codes.*.mimes' => 'Upload a JPEG, PNG, or WebP QR code image.', 'payment_qr_codes.*.max' => 'QR code images must be no larger than 2 MB.'];
    }
}
