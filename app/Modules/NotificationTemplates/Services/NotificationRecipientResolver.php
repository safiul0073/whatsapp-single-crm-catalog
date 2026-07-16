<?php

namespace App\Modules\NotificationTemplates\Services;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class NotificationRecipientResolver
{
    public function resolve(string $recipientType, ?int $roleId = null): Collection
    {
        return match ($recipientType) {
            'all_admins' => Admin::query()->where('is_active', true)->get(),
            'all_users' => User::query()->where('is_active', true)->get(),
            'role' => $this->resolveByRole((int) $roleId),
            default => new Collection,
        };
    }

    protected function resolveByRole(int $roleId): Collection
    {
        if ($roleId === 0) {
            return new Collection;
        }

        $role = Role::query()->findOrFail($roleId);

        if ($role->guard_name === 'admin') {
            return Admin::query()
                ->role($role->name)
                ->where('is_active', true)
                ->get();
        }

        return User::query()
            ->role($role->name)
            ->where('is_active', true)
            ->get();
    }
}
