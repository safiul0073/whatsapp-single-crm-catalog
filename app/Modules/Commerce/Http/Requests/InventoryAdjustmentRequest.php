<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Services\InventoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.products.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['set', 'add'])],
            'quantity' => ['required', 'integer', 'min:'.($this->input('mode') === 'set' ? 0 : -100000), 'max:100000'],
            'reason' => ['required', Rule::in(InventoryService::ADJUSTMENT_TYPES)],
            'note' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.min' => 'Stock cannot be set below zero.',
            'quantity.max' => 'Enter at most 100,000 units at a time.',
        ];
    }
}
