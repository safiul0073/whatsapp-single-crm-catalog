<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Rules\CountryCode;
use App\Rules\InternationalPhone;
use Illuminate\Foundation\Http\FormRequest;

class PosContactAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.pos.use') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country' => strtoupper((string) $this->input('country', 'US')), 'phone' => InternationalPhone::normalize($this->input('phone')) ?? $this->input('phone')]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', new InternationalPhone],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['required', new CountryCode],
        ];
    }
}
