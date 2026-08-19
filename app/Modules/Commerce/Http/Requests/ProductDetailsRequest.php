<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $features = $this->input('features');
        if (is_string($features)) {
            $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $features))));
        }

        $featureHighlights = $this->input('feature_highlights');
        if (is_string($featureHighlights)) {
            $featureHighlights = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $featureHighlights))));
        }

        $this->merge([
            'features' => $features ?: null,
            'feature_highlights' => $featureHighlights ?: null,
            'moq' => max(1, (int) $this->input('moq', 1)),
            'condition' => $this->input('condition', 'new') ?: 'new',
            'country_of_origin' => $this->input('country_of_origin', 'BD') ?: 'BD',
            'visibility' => $this->input('visibility', 'published') ?: 'published',
            'status' => $this->input('status', 'active') ?: 'active',
        ]);
    }

    public function rules(): array
    {
        $workspaceId = app(WorkspaceResolver::class)->current($this->user())?->id;
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255', Rule::unique('commerce_products')->where('workspace_id', $workspaceId)->ignore($productId)],
            'sku' => ['nullable', 'string', 'max:120'],
            'visibility' => ['nullable', 'in:published,hidden'],
            'category_id' => ['nullable', 'integer', Rule::exists('commerce_categories', 'id')->where('workspace_id', $workspaceId)],
            'brand_id' => ['nullable', 'integer', Rule::exists('commerce_brands', 'id')->where('workspace_id', $workspaceId)->where('is_active', true)],
            'audience_id' => ['nullable', 'integer', Rule::exists('commerce_audiences', 'id')->where('workspace_id', $workspaceId)->where('is_active', true)],
            'brand' => ['nullable', 'string', 'max:120'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:16777215'],
            'care_information' => ['nullable', 'string', 'max:2000'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string', 'max:255'],
            'feature_highlights' => ['nullable', 'array'],
            'shipping_countries' => ['nullable', 'array'],
            'specifications' => ['nullable', 'array'],
            'fit' => ['nullable', 'string', 'max:120'],
            'set_includes' => ['nullable', 'string', 'max:200'],
            'gender' => ['nullable', 'string', 'max:100'],
            'season' => ['nullable', 'string', 'max:100'],
            'shipping_info' => ['nullable', 'string', 'max:200'],
            'delivery_time' => ['nullable', 'string', 'max:200'],
            'moq' => ['nullable', 'integer', 'min:1'],
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'reviews_count' => ['nullable', 'integer', 'min:0'],
            'condition' => ['nullable', 'in:new,refurbished,used'],
            'status' => ['nullable', 'in:active,draft,archived'],
            'audience' => ['nullable', 'string', 'max:80'],
            'fabric_gsm' => ['nullable', 'string', 'max:80'],
            'material' => ['nullable', 'string', 'max:120'],
            'default_unit_weight_kg' => ['nullable', 'numeric', 'min:0.001', 'max:100'],
            'single_piece_price' => ['nullable', 'numeric', 'min:0.01', 'max:9999999'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0.01', 'max:9999999'],
            'country_of_origin' => ['nullable', 'string', 'max:2'],
            'tier_prices' => ['nullable', 'array'],
            'tier_prices.*.min_quantity' => ['nullable', 'integer', 'min:1'],
            'tier_prices.*.max_quantity' => ['nullable', 'integer', 'min:1'],
            'tier_prices.*.unit_price' => ['nullable', 'numeric', 'min:0.01'],
            'tier_prices.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'colors' => ['nullable', 'array'],
            'colors.*.id' => ['nullable', 'integer'],
            'colors.*.name' => ['nullable', 'string', 'max:100'],
            'colors.*.hex_code' => ['nullable', 'string', 'max:30'],
            'colors.*.color_family' => ['nullable', 'string', 'max:50'],
            'colors.*.swatch_media_id' => ['nullable', 'integer', 'exists:media,id'],
        ];
    }
}
