<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignCrmLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        return ['assigned_to' => ['required', 'integer']];
    }

    public function messages(): array
    {
        return ['assigned_to.required' => __('Choose a workspace team member.')];
    }
}
