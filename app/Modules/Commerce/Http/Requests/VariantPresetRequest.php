<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VariantPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $values = $this->input('values', []);
        if (is_string($values)) {
            $values = array_values(array_filter(array_map('trim', explode(',', $values))));
        } elseif ($this->filled('values_csv')) {
            $values = array_values(array_filter(array_map('trim', explode(',', (string) $this->input('values_csv')))));
        }

        $this->merge([
            'values' => $values,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function rules(): array
    {
        $workspaceId = app(WorkspaceResolver::class)->current($this->user())?->id;
        $presetId = $this->route('preset')?->id ?? $this->route('variant')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('commerce_variant_presets')->where('workspace_id', $workspaceId)->ignore($presetId),
            ],
            'type' => ['nullable', 'string', 'max:40'],
            'values' => ['required', 'array', 'min:1'],
            'values.*' => ['required', 'string', 'max:60'],
            'is_active' => ['boolean'],
        ];
    }
}
