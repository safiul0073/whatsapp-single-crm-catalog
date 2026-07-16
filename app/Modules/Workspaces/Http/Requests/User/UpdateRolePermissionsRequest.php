<?php

namespace App\Modules\Workspaces\Http\Requests\User;

use App\Modules\Shared\Support\PermissionRegistrar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('team.manage') === true;
    }

    public function rules(): array
    {
        $permissions = app(PermissionRegistrar::class)->permissionsForGuard('web');

        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($permissions)],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.array' => __('Permissions must be submitted as a list.'),
            'permissions.*.in' => __('One or more selected permissions are invalid.'),
        ];
    }
}
