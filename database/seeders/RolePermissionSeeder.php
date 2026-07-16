<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permission:sync');
        $this->command->info('Permissions synced via permission:sync command.');
    }
}
