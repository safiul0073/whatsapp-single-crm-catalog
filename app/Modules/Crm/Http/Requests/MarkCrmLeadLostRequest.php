<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkCrmLeadLostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        return ['lost_reason' => ['nullable', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return ['lost_reason.max' => __('The lost reason must not exceed 2000 characters.')];
    }
}
