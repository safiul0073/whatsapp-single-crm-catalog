<?php

namespace App\Modules\Leads\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkConvertLeadsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'tag_ids' => array_values(array_filter((array) $this->input('tag_ids', []))),
            'group_ids' => array_values(array_filter((array) $this->input('group_ids', []))),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:contact_tags,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', 'exists:contact_groups,id'],
        ];
    }
}
