<?php

namespace App\Console\Commands;

use App\Modules\Shared\Support\PermissionRegistrar as ModulePermissionRegistrar;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar as SpatiePermissionRegistrar;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permission:sync {--fresh : Delete all existing permissions and roles before syncing}';

    protected $aliases = ['module:sync-permissions'];

    protected $description = 'Sync module permissions and configured roles to the database';

    public function handle(ModulePermissionRegistrar $modules): int
    {
        $permissions = $modules->permissions();
        $roles = config('permissions.roles', []);

        if ($permissions === []) {
            $this->error('No module permissions were discovered.');

            return self::FAILURE;
        }

        app(SpatiePermissionRegistrar::class)->forgetCachedPermissions();

        if ($this->option('fresh')) {
            $this->warn('Fresh mode: removing all existing permissions and roles...');
            Permission::query()->delete();
            Role::query()->delete();
            $this->info('All existing permissions and roles deleted.');
            $this->newLine();
        }

        $this->syncPermissions($permissions);
        $this->newLine();
        $this->syncRoles($roles, $modules);
        $this->newLine();

        app(SpatiePermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('Permission cache cleared.');
        $this->newLine();
        $this->info('Done!');

        return self::SUCCESS;
    }

    protected function syncPermissions(array $permissions): void
    {
        $this->info('Syncing permissions...');

        $newCount = 0;
        $existingCount = 0;
        $allPermissions = [];

        foreach ($permissions as $permission) {
            $allPermissions[] = [
                'name' => $permission['name'],
                'guard' => $permission['guard'],
            ];

            $existing = Permission::where('name', $permission['name'])
                ->where('guard_name', $permission['guard'])
                ->first();

            if ($existing) {
                $existingCount++;

                continue;
            }

            Permission::create([
                'name' => $permission['name'],
                'guard_name' => $permission['guard'],
            ]);

            $this->line("  <info>Created permission:</info> {$permission['name']} ({$permission['guard']})");
            $newCount++;
        }

        $total = $newCount + $existingCount;
        $this->line("  <info>Permissions synced:</info> {$total} total ({$newCount} new, {$existingCount} existing)");

        if (! $this->option('fresh')) {
            $this->removeOrphanedPermissions($allPermissions);
        }
    }

    protected function removeOrphanedPermissions(array $knownPermissions): void
    {
        $dbPermissions = Permission::all();
        $removedCount = 0;

        foreach ($dbPermissions as $dbPermission) {
            $found = false;

            foreach ($knownPermissions as $permission) {
                if ($dbPermission->name === $permission['name'] && $dbPermission->guard_name === $permission['guard']) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $this->warn("  Orphaned permission found: {$dbPermission->name} ({$dbPermission->guard_name}) - use --fresh to remove");
                $removedCount++;
            }
        }

        if ($removedCount > 0) {
            $this->line("  <comment>{$removedCount} orphaned permission(s) found. Run with --fresh to clean up.</comment>");
        }
    }

    protected function syncRoles(array $roles, ModulePermissionRegistrar $permissions): void
    {
        $this->info('Syncing roles...');

        foreach ($roles as $roleName => $roleConfig) {
            $guard = $roleConfig['guard'];
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guard,
            ]);

            $configuredPermissions = $roleConfig['permissions'];

            if (empty($configuredPermissions)) {
                $role->syncPermissions([]);
                $this->line("  <info>Role {$roleName} synced</info> ({$guard}) - Gate::before bypass");

                continue;
            }

            if ($configuredPermissions === '*') {
                $guardPermissions = $permissions->permissionsForGuard($guard);
                $role->syncPermissions($guardPermissions);
                $count = count($guardPermissions);
                $this->line("  <info>Role {$roleName} synced</info> ({$guard}) - {$count} permissions");

                continue;
            }

            if (is_array($configuredPermissions)) {
                $role->syncPermissions($configuredPermissions);
                $count = count($configuredPermissions);
                $this->line("  <info>Role {$roleName} synced</info> ({$guard}) - {$count} permissions");
            }
        }
    }
}
