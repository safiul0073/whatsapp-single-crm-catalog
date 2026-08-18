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

    public function rules(): array
    {
        $workspaceId = app(WorkspaceResolver::class)->current($this->user())?->id;
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255', Rule::unique('commerce_products')->where('workspace_id', $workspaceId)->ignore($productId)],
            'category_id' => ['nullable', 'integer', Rule::exists('commerce_categories', 'id')->where('workspace_id', $workspaceId)],
            'brand_id' => ['nullable', 'integer', Rule::exists('commerce_brands', 'id')->where('workspace_id', $workspaceId)->where('is_active', true)],
            'audience_id' => ['nullable', 'integer', Rule::exists('commerce_audiences', 'id')->where('workspace_id', $workspaceId)->where('is_active', true)],
            'brand' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'care_information' => ['nullable', 'string', 'max:2000'],
            'condition' => ['required', 'in:new,refurbished,used'],
            'audience' => ['nullable', 'string', 'max:80'],
            'fabric_gsm' => ['nullable', 'string', 'max:80'],
            'material' => ['nullable', 'string', 'max:120'],
            'default_unit_weight_kg' => ['nullable', 'numeric', 'min:0.001', 'max:100'],
            'single_piece_price' => ['nullable', 'numeric', 'min:0.01', 'max:9999999'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0.01', 'max:9999999'],
            'country_of_origin' => ['required', 'string', 'size:2'],
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
