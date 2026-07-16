<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        $workspaceId = app(WorkspaceResolver::class)->current($this->user())?->id;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('commerce_brands')->where('workspace_id', $workspaceId)->ignore($this->route('brand')?->id)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
