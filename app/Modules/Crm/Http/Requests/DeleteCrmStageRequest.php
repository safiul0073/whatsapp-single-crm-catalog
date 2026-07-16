<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCrmStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        return ['replacement_stage_id' => ['nullable', 'integer']];
    }

    public function messages(): array
    {
        return ['replacement_stage_id.integer' => __('Choose a valid replacement stage.')];
    }
}
