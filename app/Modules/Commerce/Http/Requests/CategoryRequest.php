<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        $workspaceId = app(WorkspaceResolver::class)->current($this->user())?->id;
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', Rule::exists('commerce_categories', 'id')->where('workspace_id', $workspaceId), Rule::notIn(array_filter([$categoryId]))],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
