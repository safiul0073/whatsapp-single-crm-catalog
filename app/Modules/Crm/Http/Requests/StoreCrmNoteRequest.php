<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCrmNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        return ['description' => ['required', 'string', 'max:5000']];
    }

    public function messages(): array
    {
        return ['description.required' => __('Write a note before saving.')];
    }
}
