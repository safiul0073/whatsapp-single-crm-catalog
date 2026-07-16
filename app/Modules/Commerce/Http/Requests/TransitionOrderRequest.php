<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransitionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'in:requested,needs_details,quoted,awaiting_payment,paid,processing,shipped,completed,cancelled'], 'tracking_number' => ['nullable', 'string', 'max:150'], 'tracking_url' => ['nullable', 'url:https', 'max:2048']];
    }
}
