<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PackTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user())->id;

        return [
            '_edit_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'],
            'published' => ['required', 'boolean'], 'position' => ['required', 'integer', 'min:0', 'max:10000'],
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('commerce_products', 'id')->where(fn ($q) => $q->where('workspace_id', $workspace)->where('ws_enabled', true)->whereIn('selling_mode', ['wholesale', 'both']))],
            'min_pieces' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_pieces' => ['nullable', 'integer', 'gte:min_pieces', 'max:100000'],
            'min_products' => ['required', 'integer', 'min:1', 'max:200'],
            'min_colors' => ['required', 'integer', 'min:1', 'max:100'],
            'min_sizes' => ['required', 'integer', 'min:1', 'max:20'],
            'min_variant_quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->integer('min_products') > count($this->input('product_ids', []))) {
                $validator->errors()->add('min_products', 'Select at least this many eligible products.');
            }
            $minimum = $this->integer('min_products') * $this->integer('min_colors') * $this->integer('min_sizes') * $this->integer('min_variant_quantity');
            if ($minimum > 100000 || ($this->filled('max_pieces') && $this->integer('max_pieces') < $minimum)) {
                $validator->errors()->add('max_pieces', 'The maximum cannot accommodate the required products, colors, sizes and quantities.');
            }
        }];
    }
}
