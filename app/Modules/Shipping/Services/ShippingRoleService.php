<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceRole;
use App\Modules\Workspaces\Models\WorkspaceRolePermission;

/**
 * Ready-made team roles for shipping. The workspace owner always has full access
 * and can still edit these roles from the team screen.
 */
class ShippingRoleService
{
    public const ROLES = [
        'Shipping Manager' => [
            'description' => 'Quotes, booking, tracking and shipping invoices',
            'permissions' => [
                'commerce.orders.view',
                'shipping.shipments.view', 'shipping.shipments.manage', 'shipping.bookings.create', 'shipping.costs.view', 'shipping.quotes.manage',
                'shipping.agents.view', 'shipping.agents.manage', 'shipping.billing.view', 'shipping.billing.manage',
                'shipping.reports.view', 'shipping.audit.view',
            ],
        ],
        'Accounts' => [
            'description' => 'Shipping invoices, payments and reconciliation',
            'permissions' => [
                'commerce.orders.view',
                'shipping.shipments.view', 'shipping.costs.view', 'shipping.agents.view',
                'shipping.billing.view', 'shipping.billing.manage', 'shipping.reports.view', 'shipping.audit.view',
            ],
        ],
        'Sales Team' => [
            'description' => 'Shipment progress and tracking, without cost details',
            'permissions' => ['commerce.orders.view', 'shipping.shipments.view'],
        ],
    ];

    /**
     * Creates any missing shipping role for the workspace. Existing roles are left as the owner set them.
     */
    public function seed(Workspace $workspace): int
    {
        $created = 0;
        foreach (self::ROLES as $name => $definition) {
            $role = WorkspaceRole::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $name],
                ['description' => $definition['description'], 'is_system' => false],
            );
            if (! $role->wasRecentlyCreated) {
                continue;
            }
            $created++;
            foreach ($definition['permissions'] as $permission) {
                WorkspaceRolePermission::query()->firstOrCreate([
                    'workspace_id' => $workspace->id,
                    'workspace_role_id' => $role->id,
                    'permission_name' => $permission,
                ]);
            }
        }

        return $created;
    }
}
