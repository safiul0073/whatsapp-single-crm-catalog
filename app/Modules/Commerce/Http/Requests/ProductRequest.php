<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $options = collect($this->input('options', []))->map(function (array $option): array {
            if (array_key_exists('values_csv', $option)) {
                $option['values'] = array_values(array_filter(array_map('trim', explode(',', (string) $option['values_csv']))));
            }

            return $option;
        })->all();
        $variants = collect($this->input('variants', []))->map(function (array $variant): array {
            $decoded = array_key_exists('attributes_json', $variant) ? json_decode((string) $variant['attributes_json'], true) : ($variant['attributes'] ?? []);
            $variant['attributes'] = is_array($decoded) ? $decoded : [];

            return $variant;
        })->all();
        $this->merge(($this->exists('options') ? ['options' => $options] : []) + ['variants' => $variants]);
    }

    public function rules(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user());
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255', Rule::unique('commerce_products')->where('workspace_id', $workspace?->id)->ignore($productId)],
            'category_id' => ['nullable', 'integer', Rule::exists('commerce_categories', 'id')->where('workspace_id', $workspace?->id)],
            'brand_id' => ['nullable', 'integer', Rule::exists('commerce_brands', 'id')->where('workspace_id', $workspace?->id)],
            'audience_id' => ['nullable', 'integer', Rule::exists('commerce_audiences', 'id')->where('workspace_id', $workspace?->id)],
            'primary_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('uploaded_by', $this->user()?->id)->where('type', 'image')],
            'brand' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'care_information' => ['nullable', 'string', 'max:2000'],
            'condition' => ['required', 'in:new,refurbished,used'],
            'audience' => ['nullable', 'string', 'max:80'],
            'country_of_origin' => ['required', 'string', 'size:2'],
            'status' => ['required', 'in:draft,active,archived'],
            'options' => ['array'],
            'options.*.name' => ['nullable', 'string', 'max:80'],
            'options.*.code' => ['nullable', 'string', 'max:80'],
            'options.*.values' => ['array'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'max:120', 'distinct', Rule::unique('commerce_product_variants', 'sku')->where('workspace_id', $workspace?->id)->whereNotIn('id', collect($this->input('variants'))->pluck('id')->filter()->all())],
            'variants.*.meta_retailer_id' => ['nullable', 'string', 'max:120', 'distinct'],
            'variants.*.id' => ['nullable', 'integer', Rule::exists('commerce_product_variants', 'id')->where('product_id', $productId)],
            'variants.*.media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('uploaded_by', $this->user()?->id)->where('type', 'image')],
            'variants.*.attributes' => ['array'],
            'variants.*.attributes_json' => ['sometimes', 'json'],
            'variants.*.price' => ['required', 'numeric', 'min:0.01'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0.01'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variants.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'variants.*.status' => ['required', 'in:active,out_of_stock,archived'],
        ];
    }
}
