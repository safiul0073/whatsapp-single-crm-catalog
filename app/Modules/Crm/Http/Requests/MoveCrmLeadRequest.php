<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveCrmLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        return ['stage_id' => ['required', 'integer']];
    }

    public function messages(): array
    {
        return ['stage_id.required' => __('Choose a destination stage.')];
    }
}
