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
        return ['web' => [
            'commerce.orders.view' => 'View orders',
            'commerce.orders.create' => 'Create orders',
            'commerce.orders.update' => 'Update order status, quotes and packing',
            'commerce.orders.summary.view' => 'View order summary and pricing',
            'commerce.orders.customer.view' => 'View order customer details',
            'commerce.orders.customer.edit' => 'Edit order customer details',
            'commerce.orders.payments.view' => 'View order payments and proof',
            'commerce.orders.payments.manage' => 'Record order payments',
            'commerce.orders.settings' => 'Manage order and payment settings',
            'commerce.products.view' => 'View products and catalog data',
            'commerce.products.manage' => 'Manage products, categories and brands',
            'commerce.catalog.manage' => 'Manage Meta catalog and product messaging',
            'commerce.pos.use' => 'Use POS',
            'commerce.customers.view' => 'View store customers',
            'commerce.customers.manage' => 'Manage store customers',
            'commerce.storefront.settings' => 'Manage storefront settings',
            'commerce.storefront.pages' => 'Manage storefront pages and content',
            'commerce.storefront.marketing' => 'Manage sliders, menus and coupons',
            'commerce.storefront.domains' => 'Manage storefront domains',
            'commerce.storefront.reviews' => 'Moderate product reviews',
            'commerce.storefront.credentials' => 'Manage storefront credentials',
            'commerce.referrals.view' => 'View referrals',
            'commerce.referrals.manage' => 'Manage referrals',
            'commerce.pack-templates.manage' => 'Manage pack templates',
        ]];
    }

    public function policies(): array
    {
        return [Product::class => CommercePolicy::class];
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Sales')->item('Commerce', 'user.commerce.*', 'shopping-bag', 'commerce.orders.view|commerce.products.view|commerce.customers.view|commerce.pos.use|commerce.storefront.settings', 38, [
            ['label' => 'Products', 'route' => 'user.commerce.products.index'],
            ['label' => 'POS', 'route' => 'user.commerce.pos.index'],
            ['label' => 'Orders', 'route' => 'user.commerce.orders.index'],
            ['label' => 'Customers', 'route' => 'user.commerce.customers.index'],
            ['label' => 'Storefront', 'route' => 'user.commerce.storefront.index'],
            ['label' => 'Referrals', 'route' => 'user.commerce.referrals.index'],
            ['label' => 'Meta Catalog', 'route' => 'user.commerce.catalog'],
            ['label' => 'Shipping', 'route' => 'user.shipping.*'],
        ]);
    }
}
