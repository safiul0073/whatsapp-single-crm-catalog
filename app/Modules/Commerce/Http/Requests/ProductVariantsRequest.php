<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'variants' => ['required', 'array', 'min:1', 'max:500'],
            'variants.*.id' => ['nullable', 'integer', Rule::exists('commerce_product_variants', 'id')->where('product_id', $product?->id)],
            'variants.*.sku' => ['required', 'string', 'max:120', 'distinct', Rule::unique('commerce_product_variants', 'sku')->where('workspace_id', $product?->workspace_id)->whereNotIn('id', collect($this->input('variants'))->pluck('id')->filter()->all())],
            'variants.*.meta_retailer_id' => ['required', 'string', 'max:120', 'distinct'],
            'variants.*.attributes' => ['required', 'array'],
            'variants.*.media_id' => ['nullable', 'integer', Rule::exists('commerce_product_media', 'media_id')->where('product_id', $product?->id)->where('media_type', 'image')],
            'variants.*.price' => ['required', 'numeric', 'min:0.01'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'gt:variants.*.price'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variants.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'variants.*.package_dimensions' => ['nullable', 'array'],
            'variants.*.package_dimensions.length' => ['nullable', 'numeric', 'min:0'],
            'variants.*.package_dimensions.width' => ['nullable', 'numeric', 'min:0'],
            'variants.*.package_dimensions.height' => ['nullable', 'numeric', 'min:0'],
            'variants.*.status' => ['required', 'in:active,out_of_stock,archived'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $variants = collect($this->input('variants', []))->map(function (array $variant): array {
            $attributes = json_decode((string) ($variant['attributes_json'] ?? '{}'), true);
            $variant['attributes'] = is_array($attributes) ? $attributes : [];

            return $variant;
        })->all();

        $this->merge(['variants' => $variants]);
    }
}
