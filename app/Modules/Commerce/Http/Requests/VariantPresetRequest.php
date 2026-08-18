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
        $name = trim((string) $this->input('name', ''));
        $skuSuffix = trim((string) $this->input('sku_suffix', ''));
        if ($skuSuffix === '' && $name !== '') {
            $skuSuffix = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $name)) ?: $name;
        }

        $values = $this->input('values', []);
        if (is_string($values)) {
            $values = array_values(array_filter(array_map('trim', explode(',', $values))));
        } elseif ($this->filled('values_csv')) {
            $values = array_values(array_filter(array_map('trim', explode(',', (string) $this->input('values_csv')))));
        }

        if (empty($values) && ($skuSuffix !== '' || $name !== '')) {
            $values = [$skuSuffix ?: $name];
        }

        $this->merge([
            'sku_suffix' => $skuSuffix !== '' ? $skuSuffix : null,
            'price_delta' => $this->filled('price_delta') ? (float) $this->input('price_delta') : 0.00,
            'values' => $values,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
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
            'sku_suffix' => ['nullable', 'string', 'max:40'],
            'price_delta' => ['nullable', 'numeric'],
            'type' => ['nullable', 'string', 'max:40'],
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable', 'string', 'max:60'],
            'is_active' => ['boolean'],
        ];
    }
}
