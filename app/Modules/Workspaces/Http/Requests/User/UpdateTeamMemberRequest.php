<?php

namespace App\Modules\Workspaces\Http\Requests\User;

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user());

        return $workspace?->isOwner($this->user()) === true;
    }

    public function rules(): array
    {
        /** @var User $member */
        $member = $this->route('member');

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member->id)],
            'role' => ['sometimes', 'required', Rule::in(WorkspaceMemberRole::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => __('Please provide a first name.'),
            'last_name.required' => __('Please provide a last name.'),
            'email.required' => __('Please provide an email address.'),
            'email.unique' => __('This email address is already in use.'),
            'role.required' => __('Please select a role.'),
            'role.in' => __('The selected role is invalid.'),
        ];
    }
}
