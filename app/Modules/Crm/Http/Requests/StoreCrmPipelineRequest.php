<?php

namespace App\Modules\Crm\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        $pipelineId = $this->route('pipeline');
        $workspace = app(WorkspaceResolver::class)->current($this->user());

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('crm_pipelines', 'name')
                    ->where(fn ($query) => $query->where('workspace_id', $workspace?->id ?? 0))
                    ->ignore($pipelineId),
            ],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Give the pipeline a name.'),
            'name.unique' => __('A pipeline with this name already exists.'),
        ];
    }
}
