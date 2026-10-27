<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Models\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.products.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'layout' => ['required', Rule::in(ProductType::LAYOUTS)],
            'variant_axes' => ['nullable', 'array', 'max:5'],
            'variant_axes.*' => ['string', Rule::in(ProductType::VARIANT_AXES)],
            'attributes' => ['nullable', 'array', 'max:25'],
            'attributes.*.label' => ['required', 'string', 'max:80'],
            'attributes.*.type' => ['required', Rule::in(['text', 'number', 'select', 'boolean'])],
            'attributes.*.options' => ['nullable', 'string', 'max:500'],
            'attributes.*.required' => ['nullable', 'boolean'],
            'attributes.*.filterable' => ['nullable', 'boolean'],
            'attributes.*.show_on_card' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'attributes.*.label.required' => __('Every attribute needs a label.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function typeData(): array
    {
        $keys = [];
        $attributes = [];
        foreach ((array) $this->input('attributes', []) as $row) {
            $base = Str::slug($row['label'], '_') ?: 'attribute';
            $key = $base;
            for ($i = 2; in_array($key, $keys, true); $i++) {
                $key = $base.'_'.$i;
            }
            $keys[] = $key;
            $options = $row['type'] === 'select'
                ? array_values(array_filter(array_map('trim', explode(',', (string) ($row['options'] ?? '')))))
                : [];
            $attributes[] = [
                'key' => $key,
                'label' => $row['label'],
                'type' => $row['type'],
                'options' => $options,
                'required' => ! empty($row['required']),
                'filterable' => ! empty($row['filterable']),
                'show_on_card' => ! empty($row['show_on_card']),
            ];
        }

        return [
            'name' => $this->string('name')->toString(),
            'layout' => $this->string('layout')->toString(),
            'variant_axes' => array_values($this->input('variant_axes', [])),
            'attributes' => $attributes,
        ];
    }
}
