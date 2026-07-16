<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        $workspaceId = app(WorkspaceResolver::class)->current($this->user())?->id;

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('commerce_audiences')->where('workspace_id', $workspaceId)->ignore($this->route('audience')?->id)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
