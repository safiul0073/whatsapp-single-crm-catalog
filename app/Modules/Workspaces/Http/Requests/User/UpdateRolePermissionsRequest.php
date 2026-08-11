<?php

namespace App\Modules\Workspaces\Http\Requests\User;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shared\Support\PermissionRegistrar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user());

        return $workspace?->isOwner($this->user()) === true;
    }

    public function rules(): array
    {
        $permissions = collect(app(PermissionRegistrar::class)->permissionsForGuard('web'))
            ->reject(fn (string $permission): bool => in_array($permission, ['team.manage', 'team.manage.staff_only'], true))
            ->values()
            ->all();

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
