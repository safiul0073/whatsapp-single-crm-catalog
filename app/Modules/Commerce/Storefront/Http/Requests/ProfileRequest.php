<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use App\Rules\CountryCode;
use App\Rules\InternationalPhone;
use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_customer');
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('address');
        if (is_array($address)) {
            $address['phone'] = InternationalPhone::normalize($address['phone'] ?? null) ?? ($address['phone'] ?? null);
            $address['country'] = strtoupper(trim((string) ($address['country'] ?? '')));
            $this->merge(['address' => $address]);
        }
    }

    public function rules(): array
    {
        if ($this->route()->getActionMethod() === 'update') {
            return [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['prohibited'],
                'address' => ['nullable', 'array'],
                'address.line1' => ['nullable', 'string', 'max:255'],
                'address.line2' => ['nullable', 'string', 'max:255'],
                'address.city' => ['nullable', 'string', 'max:120'],
                'address.state' => ['nullable', 'string', 'max:120'],
                'address.postal_code' => ['nullable', 'string', 'max:30'],
                'address.country' => ['nullable', new CountryCode],
            ];
        }

        return [
            'label' => ['required', 'string', 'max:50'],
            'address.name' => ['required', 'string', 'max:150'],
            'address.phone' => ['required', 'string', 'max:32', new InternationalPhone],
            'address.line1' => ['required', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:120'],
            'address.state' => ['nullable', 'string', 'max:120'],
            'address.postal_code' => ['nullable', 'string', 'max:30'],
            'address.country' => ['required', new CountryCode],
        ];
    }
}
