<?php

namespace App\Modules\Contacts\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user());

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('contact_tags')->where(function ($query) use ($workspace): void {
                    $query->where('workspace_id', $workspace->id);
                }),
            ],
            'color' => ['nullable', 'string', 'max:7'],
        ];
    }
}
