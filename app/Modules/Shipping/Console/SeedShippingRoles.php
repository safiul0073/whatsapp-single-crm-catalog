<?php

namespace App\Modules\Shipping\Console;

use App\Modules\Shipping\Services\ShippingRoleService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Console\Command;

class SeedShippingRoles extends Command
{
    protected $signature = 'shipping:seed-roles';

    protected $description = 'Add the Shipping Manager, Accounts and Sales Team roles to every workspace that lacks them';

    public function handle(ShippingRoleService $roles): int
    {
        $created = 0;
        Workspace::query()->chunkById(200, function ($workspaces) use ($roles, &$created): void {
            foreach ($workspaces as $workspace) {
                $created += $roles->seed($workspace);
            }
        });

        $this->info("Created {$created} shipping role(s).");

        return self::SUCCESS;
    }
}
