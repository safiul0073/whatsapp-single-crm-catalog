<?php

namespace App\Modules\Crm\Http\Requests;

use App\Modules\Crm\Enums\CrmLeadSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer'],
            'conversation_id' => ['nullable', 'integer'],
            'campaign_id' => ['nullable', 'integer'],
            'pipeline_id' => ['nullable', 'integer'],
            'stage_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'source' => ['nullable', Rule::in(CrmLeadSource::values())],
            'assigned_to' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['contact_id.required' => __('Choose a contact for this lead.')];
    }
}
