<?php

namespace App\Modules\Crm\Http\Requests;

use App\Modules\Crm\Enums\CrmTaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'integer'],
            'contact_id' => ['required', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(CrmTaskPriority::values())],
            'due_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return ['title.required' => __('Give the follow-up task a title.'), 'due_at.required' => __('Choose when this task is due.')];
    }
}
