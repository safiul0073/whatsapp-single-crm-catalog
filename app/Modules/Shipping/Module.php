<?php

namespace App\Modules\Shipping;

use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'shipping';
    }

    public function permissions(): array
    {
        return ['web' => [
            'shipping.view' => 'View shipping settings',
            'shipping.manage' => 'Manage shipping settings',
            'shipping.shipments.view' => 'View shipments and tracking',
            'shipping.shipments.manage' => 'Create shipments, cartons, booking and tracking',
            'shipping.bookings.create' => 'Book shipments and print labels through courier APIs',
            'shipping.bookings.void' => 'Approve cancelling courier bookings',
            'shipping.costs.view' => 'View internal shipping costs and quotations',
            'shipping.quotes.manage' => 'Request and enter shipping quotations',
            'shipping.quotes.approve' => 'Approve the final carrier or agent',
            'shipping.agents.view' => 'View shipping agents and rate charts',
            'shipping.agents.manage' => 'Manage shipping agents, rate charts and remote areas',
            'shipping.carriers.manage' => 'Manage courier accounts and API credentials',
            'shipping.billing.view' => 'View shipping invoices and billing',
            'shipping.billing.manage' => 'Upload courier invoices, reconcile them and record payments',
            'shipping.billing.approve' => 'Approve unexpected shipping charges',
            'shipping.reports.view' => 'View shipping cost and profit reports',
            'shipping.product_costs.manage' => 'Manage product cost prices',
            'shipping.audit.view' => 'View the shipping audit log',
        ]];
    }

    public function policies(): array
    {
        return [];
    }
}
