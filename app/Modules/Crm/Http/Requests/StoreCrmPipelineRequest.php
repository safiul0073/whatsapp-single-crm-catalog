<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCrmPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'is_default' => ['nullable', 'boolean']];
    }

    public function messages(): array
    {
        return ['name.required' => __('Give the pipeline a name.')];
    }
}
