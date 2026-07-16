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
            'country_of_origin' => ['required', 'string', 'size:2'],
        ];
    }
}
