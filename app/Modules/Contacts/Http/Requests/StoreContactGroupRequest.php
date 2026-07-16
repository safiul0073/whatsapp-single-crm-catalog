<?php

namespace App\Modules\Contacts\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user());
        $groupId = $this->route('group');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('contact_groups')->ignore($groupId)->where(function ($query) use ($workspace): void {
                    $query->where('workspace_id', $workspace->id);
                }),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['static', 'dynamic'])],
            'rules' => ['nullable', 'array'],
            'rules.*.field' => ['nullable', 'string', 'max:64'],
            'rules.*.operator' => ['nullable', 'string', 'max:32'],
            'rules.*.value' => ['nullable'],
            'rules.*.boolean' => ['nullable', Rule::in(['and', 'or'])],
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['integer'],
        ];
    }
}
