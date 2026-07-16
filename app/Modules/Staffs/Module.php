<?php

namespace App\Modules\Staffs;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'staffs';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'staffs.view' => 'View Staffs',
                'staffs.create' => 'Create Staffs',
                'staffs.edit' => 'Edit Staffs',
                'staffs.delete' => 'Delete Staffs',
                'roles.view' => 'View Roles',
                'roles.create' => 'Create Roles',
                'roles.edit' => 'Edit Roles',
                'roles.delete' => 'Delete Roles',
            ],
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('Staff Management')
            ->item(label: 'Staffs', route: 'admin.staffs.*', icon: 'ph-user-gear', permission: 'staffs.view')
            ->order(40)
            ->item(label: 'Roles', route: 'admin.roles.*', icon: 'ph-shield-check', permission: 'roles.view')
            ->order(41);
    }
}
