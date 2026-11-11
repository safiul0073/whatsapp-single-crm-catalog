<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.products.manage') ?? false;
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

            $formattedValues = [];
            foreach ($values as $v) {
                if (is_array($v) && isset($v['value'])) {
                    $val = trim((string) $v['value']);
                    if ($val !== '') {
                        $formattedValues[$val] = $v;
                        $formattedValues[$val]['value'] = $val;
                    }
                } elseif (is_string($v)) {
                    $val = trim($v);
                    if ($val !== '') {
                        if (! isset($formattedValues[$val])) {
                            $formattedValues[$val] = $val;
                        }
                    }
                }
            }
            $option['values'] = array_values($formattedValues);

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
                    'weight' => $size['weight'] ?? null,
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
            if ($fs['value'] !== '' && ! isset($uniqueSizes[$fs['value']])) {
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
            'options.*.values.*' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                $rules = is_array($value)
                    ? ['value' => ['required', 'string', 'max:80'], 'weight' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'weight_unit' => ['nullable', 'in:kg,g,lb,oz']]
                    : ['value' => ['required', 'string', 'max:80']];
                $validation = Validator::make(is_array($value) ? $value : ['value' => $value], $rules);
                if ($validation->fails()) {
                    $fail($validation->errors()->first());
                }
            }],
            'sizes' => ['nullable'],
            'sizes_csv' => ['nullable', 'string'],
            'colors' => ['nullable', 'array'],
            'colors.*.id' => ['nullable', 'integer', Rule::exists('commerce_product_colors', 'id')->where('product_id', $this->route('product')?->id)],
            'colors.*.name' => ['nullable', 'string', 'max:100'],
            'colors.*.hex_code' => ['nullable', 'string', 'max:30'],
            'colors.*.color_family' => ['nullable', 'string', 'max:50'],
            'colors.*.swatch_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('uploaded_by', $this->user()?->id)->where('type', 'image')],
            'size_chart' => ['nullable', 'array'],
            'size_chart.unit' => ['nullable', 'in:cm,inch'],
            'size_chart.columns' => ['nullable', 'array', 'max:8'],
            'size_chart.columns.*' => ['nullable', 'string', 'max:30'],
            'size_chart.rows' => ['nullable', 'array', 'max:30'],
            'size_chart.rows.*.size' => ['required', 'string', 'max:80'],
            'size_chart.rows.*.values' => ['nullable', 'array', 'max:8'],
            'size_chart.rows.*.values.*' => ['nullable', 'string', 'max:10'],
            'size_chart.note' => ['nullable', 'string', 'max:300'],
            'next_step' => ['sometimes', 'integer', 'between:1,9'],
        ];
    }

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            $sizeOption = collect($this->input('options', []))->firstWhere('code', 'size');
            $sizes = collect($sizeOption['values'] ?? [])->map(fn (mixed $v): string => (string) (is_array($v) ? ($v['value'] ?? '') : $v))->all();
            foreach ((array) $this->input('size_chart.rows', []) as $index => $row) {
                $size = is_array($row) ? (string) ($row['size'] ?? '') : '';
                if ($size !== '' && ! in_array($size, $sizes, true)) {
                    $validator->errors()->add("size_chart.rows.{$index}.size", __('The size chart row ":size" is not one of this product\'s sizes.', ['size' => $size]));
                }
            }
        }];
    }
}
