<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OwnerCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.customers.manage') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'variant_id' => [$this->isMethod('post') ? 'required' : 'prohibited', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variant_id.required' => 'Choose a product size to add.',
            'quantity.max' => 'A cart line can hold at most 10,000 pieces.',
        ];
    }
}
