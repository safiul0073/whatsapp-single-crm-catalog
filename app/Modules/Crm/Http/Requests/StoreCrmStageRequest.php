<?php

namespace App\Modules\Crm\Http\Requests;

use App\Modules\Crm\Models\CrmStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        $stageId = $this->route('stage');
        $stage = filled($stageId) ? CrmStage::query()->find($stageId) : null;
        $pipelineId = $stage?->pipeline_id ?? (int) $this->route('pipeline');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('crm_stages', 'name')
                    ->where(fn ($query) => $query->where('pipeline_id', $pipelineId))
                    ->ignore($stage?->id),
            ],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Give the stage a name.'),
            'name.unique' => __('A stage with this name already exists in this pipeline.'),
            'color.regex' => __('Use a six-digit hexadecimal color.'),
        ];
    }
}
