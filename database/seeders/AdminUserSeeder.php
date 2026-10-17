<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Enums\WorkspaceStatus;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('super-admin', 'admin');
        Role::findOrCreate('user', 'web');

        /*
        |--------------------------------------------------------------------------
        | Admin Panel — Master Super Admin (admins table, admin guard)
        |--------------------------------------------------------------------------
        */
        $superAdmin = Admin::firstOrCreate(
            ['email' => 'admin@mail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        if (! $superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole('super-admin');
        }

        /*
        |--------------------------------------------------------------------------
        | User Panel — Default User (users table, web guard)
        |--------------------------------------------------------------------------
        */
        $user = User::firstOrCreate(
            ['email' => 'user@mail.com'],
            [
                'name' => 'Application Owner',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $user->syncRoles(['user']);

        /*
        |--------------------------------------------------------------------------
        | Default Workspace for Application Owner
        |--------------------------------------------------------------------------
        */
        $workspace = Workspace::firstOrCreate(
            ['owner_id' => $user->id],
            [
                'name' => 'Default Store',
                'slug' => 'default-store',
                'status' => WorkspaceStatus::Active,
                'timezone' => config('app.timezone', 'UTC'),
            ]
        );

        $adminRole = WorkspaceRole::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Administrator'],
            ['description' => 'Full access to the workspace', 'is_system' => true]
        );

        WorkspaceRole::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Manager'],
            ['description' => 'Can manage most settings', 'is_system' => true]
        );

        WorkspaceRole::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Staff'],
            ['description' => 'Standard user access', 'is_system' => true]
        );

        if (! $workspace->members()->where('user_id', $user->id)->exists()) {
            $workspace->members()->attach($user->id, [
                'workspace_role_id' => $adminRole->id,
                'status' => WorkspaceMemberStatus::Active->value,
            ]);
        }
    }
}
