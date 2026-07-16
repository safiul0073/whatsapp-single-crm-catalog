<?php

namespace App\Modules\Workspaces\Http\Requests\User;

use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('team.manage') || $this->user()?->can('team.manage.staff_only');
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(WorkspaceMemberRole::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('Please provide an email address.'),
            'role.required' => __('Please select a role.'),
            'role.in' => __('The selected role is invalid.'),
        ];
    }
}
