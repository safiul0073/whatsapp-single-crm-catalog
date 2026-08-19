<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $options = collect($this->input('options', []))->map(function (array $option): array {
            $values = $option['values'] ?? [];
            if (is_string($values)) {
                $values = [$values];
            } elseif (! is_array($values)) {
                $values = [];
            }

            if (empty($values) && isset($option['values_csv'])) {
                $values = explode(',', (string) $option['values_csv']);
            }

            $option['values'] = array_values(array_unique(array_filter(array_map('trim', $values))));

            return $option;
        })->all();

        // If simple sizes / sizes_csv is provided, auto-construct the Size and Color options
        $sizes = $this->input('sizes', []);
        if (is_string($sizes)) {
            $sizes = array_filter(array_map('trim', explode(',', $sizes)));
        } elseif ($this->filled('sizes_csv')) {
            $sizes = array_filter(array_map('trim', explode(',', (string) $this->input('sizes_csv'))));
        }

        $formattedSizes = [];
        foreach ($sizes as $size) {
            if (is_array($size) && isset($size['value'])) {
                $formattedSizes[] = [
                    'value' => trim((string) $size['value']),
                    'weight' => isset($size['weight']) ? (float) $size['weight'] : null,
                    'weight_unit' => $size['weight_unit'] ?? 'kg',
                ];
            } elseif (is_string($size)) {
                $formattedSizes[] = [
                    'value' => trim($size),
                    'weight' => null,
                    'weight_unit' => 'kg',
                ];
            }
        }
        
        $uniqueSizes = [];
        foreach ($formattedSizes as $fs) {
            if ($fs['value'] !== '' && !isset($uniqueSizes[$fs['value']])) {
                $uniqueSizes[$fs['value']] = $fs;
            }
        }
        $sizes = array_values($uniqueSizes);

        if (! empty($sizes)) {
            $constructedOptions = [];
            $constructedOptions[] = [
                'name' => 'Size',
                'code' => 'size',
                'values' => $sizes,
            ];

            $colors = $this->input('colors', []);
            $colorNames = [];
            foreach ($colors as $c) {
                $name = trim((string) ($c['name'] ?? ''));
                if ($name === '') {
                    $name = trim((string) ($c['hex_code'] ?? ''));
                }
                if ($name !== '') {
                    $colorNames[] = $name;
                }
            }
            $colorNames = array_values(array_unique($colorNames));

            if (! empty($colorNames)) {
                $constructedOptions[] = [
                    'name' => 'Color',
                    'code' => 'color',
                    'values' => $colorNames,
                ];
            }

            $options = $constructedOptions;
        }

        $this->merge(['options' => $options]);
    }

    public function rules(): array
    {
        return [
            'options' => ['required', 'array', 'min:1', 'max:5'],
            'options.*.name' => ['required', 'string', 'max:80', 'distinct'],
            'options.*.code' => ['required', 'alpha_dash', 'max:80', 'distinct'],
            'options.*.values' => ['required', 'array', 'min:1', 'max:30'],
            'options.*.values.*' => ['required', 'string', 'max:80'],
            'sizes' => ['nullable'],
            'sizes_csv' => ['nullable', 'string'],
            'colors' => ['nullable', 'array'],
            'colors.*.id' => ['nullable', 'integer'],
            'colors.*.name' => ['nullable', 'string', 'max:100'],
            'colors.*.hex_code' => ['nullable', 'string', 'max:30'],
            'colors.*.color_family' => ['nullable', 'string', 'max:50'],
            'colors.*.swatch_media_id' => ['nullable', 'integer', 'exists:media,id'],
        ];
    }
}
