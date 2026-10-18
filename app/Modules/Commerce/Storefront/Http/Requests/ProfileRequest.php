<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_customer');
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
                'address.country' => ['nullable', 'string', 'max:100'],
            ];
        }

        return [
            'label' => ['required', 'string', 'max:50'],
            'address.name' => ['required', 'string', 'max:150'],
            'address.phone' => ['required', 'string', 'max:30'],
            'address.line1' => ['required', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:120'],
            'address.state' => ['nullable', 'string', 'max:120'],
            'address.postal_code' => ['nullable', 'string', 'max:30'],
            'address.country' => ['required', 'string', 'max:100'],
        ];
    }
}
