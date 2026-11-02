<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Rules\CountryCode;
use App\Rules\InternationalPhone;
use Illuminate\Foundation\Http\FormRequest;

class PosCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.pos.use') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => InternationalPhone::normalize($this->input('phone')) ?? $this->input('phone')]);
        if (is_array($this->input('address'))) {
            $address = $this->input('address');
            $this->merge(['address' => [...$address, 'phone' => InternationalPhone::normalize($address['phone'] ?? null) ?? ($address['phone'] ?? null), 'country' => strtoupper((string) ($address['country'] ?? 'US'))]]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', new InternationalPhone],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'array'],
            ...$this->addressRules('address.'),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function addressRules(string $prefix = ''): array
    {
        return [
            $prefix.'name' => ['required_with:'.rtrim($prefix, '.'), 'nullable', 'string', 'max:150'],
            $prefix.'phone' => ['required_with:'.rtrim($prefix, '.'), 'nullable', 'string', 'max:30', new InternationalPhone],
            $prefix.'line1' => ['required_with:'.rtrim($prefix, '.'), 'nullable', 'string', 'max:255'],
            $prefix.'line2' => ['nullable', 'string', 'max:255'],
            $prefix.'city' => ['required_with:'.rtrim($prefix, '.'), 'nullable', 'string', 'max:120'],
            $prefix.'state' => ['nullable', 'string', 'max:120'],
            $prefix.'postal_code' => ['nullable', 'string', 'max:30'],
            $prefix.'country' => ['nullable', new CountryCode],
        ];
    }
}
