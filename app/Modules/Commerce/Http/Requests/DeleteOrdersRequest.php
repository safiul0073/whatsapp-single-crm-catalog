<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;

class DeleteOrdersRequest extends FormRequest
{
    /** Only the workspace owner may delete orders; team members never can. */
    public function authorize(): bool
    {
        $user = $this->user();
        $workspace = $user ? app(WorkspaceResolver::class)->current($user) : null;

        return $workspace !== null && $workspace->isOwner($user);
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => __('Select at least one order to delete.'),
            'ids.max' => __('You can delete up to 100 orders at a time.'),
        ];
    }
}
