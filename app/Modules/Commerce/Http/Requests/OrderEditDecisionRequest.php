<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderEditDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.orders.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject,revoke'],
            'owner_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
