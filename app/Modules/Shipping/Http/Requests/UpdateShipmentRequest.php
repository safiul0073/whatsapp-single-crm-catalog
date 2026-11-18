<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\Shipment;
use App\Rules\CountryCode;
use App\Rules\InternationalPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $country = strtoupper((string) $this->input('country_code'));
        $phone = trim((string) $this->input('phone'));
        if ($country === 'US' && $phone !== '' && ! str_starts_with($phone, '+')) {
            $phone = '+1'.preg_replace('/\D+/', '', $phone);
        }

        $this->merge(['country_code' => $country ?: null, 'phone' => $phone ?: null]);
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', new InternationalPhone],
            'country_code' => ['required', 'string', 'size:2', new CountryCode],
            'state' => ['nullable', 'string', 'max:80'],
            'city' => ['required', 'string', 'max:80'],
            'postal_code' => ['required', 'string', 'max:20'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'address_type' => ['required', Rule::in(array_keys(Shipment::ADDRESS_TYPES))],
            'total_sets' => ['required', 'integer', 'min:0', 'max:1000000'],
            'total_pieces' => ['required', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['phone.required' => __('Enter the phone number with its country code, for example +1 for the USA.')];
    }
}
