<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.orders.update') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'carrier' => ['nullable', 'string', 'max:150'],
            'tracking_number' => ['required', 'string', 'max:150'],
            'tracking_url' => ['nullable', 'url:https', 'max:2048'],
            'proof_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_proof_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proof_image.image' => __('The courier image must be a JPG, PNG or WebP image.'),
            'proof_image.max' => __('The courier image may not be larger than 5 MB.'),
        ];
    }
}
