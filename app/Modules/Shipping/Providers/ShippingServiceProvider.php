<?php

namespace App\Modules\Shipping\Providers;

use App\Modules\Commerce\Models\OrderEvent;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Modules\Shipping\Console\SeedShippingRoles;
use App\Modules\Shipping\Jobs\CreateShipmentForOrder;
use App\Modules\Shipping\Services\ShippingRoleService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\Schema;

class ShippingServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        //
    }

    protected function bootModule(array $module): void
    {
        OrderEvent::created(function (OrderEvent $event): void {
            if ($event->key === 'placed') {
                CreateShipmentForOrder::dispatch($event->order_id)->afterCommit();
            }
        });

        Workspace::created(function (Workspace $workspace): void {
            if (Schema::hasTable('workspace_roles')) {
                app(ShippingRoleService::class)->seed($workspace);
            }
        });

        $this->commands([SeedShippingRoles::class]);
    }
}
