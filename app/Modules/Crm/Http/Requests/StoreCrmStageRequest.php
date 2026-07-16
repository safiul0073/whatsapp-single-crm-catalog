<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCrmStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'position' => ['nullable', 'integer', 'min:0']];
    }

    public function messages(): array
    {
        return ['name.required' => __('Give the stage a name.'), 'color.regex' => __('Use a six-digit hexadecimal color.')];
    }
}
