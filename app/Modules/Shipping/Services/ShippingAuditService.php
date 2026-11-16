<?php

namespace App\Modules\Shipping\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingAuditEntry;

class ShippingAuditService
{
    public function __construct(private AuditLogService $audit) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(int $workspaceId, string $action, string $summary, ?Shipment $shipment = null, ?array $old = null, ?array $new = null, ?int $userId = null): ShippingAuditEntry
    {
        $entry = ShippingAuditEntry::query()->create([
            'workspace_id' => $workspaceId,
            'shipping_shipment_id' => $shipment?->id,
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'summary' => mb_substr($summary, 0, 255),
            'old_values' => $old,
            'new_values' => $new,
        ]);

        rescue(fn () => $this->audit->logCustom('shipping.'.$action, array_filter([
            'workspace_id' => $workspaceId,
            'shipment' => $shipment?->reference,
            'summary' => $summary,
        ])), report: false);

        return $entry;
    }
}
