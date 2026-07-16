<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
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

    }
}
