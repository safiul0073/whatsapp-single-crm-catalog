<?php

namespace App\Modules\Workspaces\Http\Requests\User;

use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('team.manage') || $this->user()?->can('team.manage.staff_only');
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(WorkspaceMemberRole::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => __('Please provide a first name.'),
            'last_name.required' => __('Please provide a last name.'),
            'email.required' => __('Please provide an email address.'),
            'email.unique' => __('This email address is already in use.'),
            'password.required' => __('Please provide a password.'),
            'password.min' => __('Password must be at least 8 characters.'),
            'password.confirmed' => __('Password confirmation does not match.'),
            'role.required' => __('Please select a role.'),
            'role.in' => __('The selected role is invalid.'),
        ];
    }
}
