<?php

namespace App\Modules\Commerce;

use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Policies\CommercePolicy;
use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'commerce';
    }

    public function permissions(): array
    {
        return ['web' => ['commerce.view' => 'View commerce', 'commerce.manage' => 'Manage commerce']];
    }

    public function policies(): array
    {
        return [Product::class => CommercePolicy::class];
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Sales')->item('Commerce', 'user.commerce.*', 'shopping-bag', 'commerce.view', 38, [
            ['label' => 'Products', 'route' => 'user.commerce.products.index'],
            ['label' => 'Orders', 'route' => 'user.commerce.orders.index'],
            ['label' => 'Meta Catalog', 'route' => 'user.commerce.catalog'],
        ]);
    }
}
