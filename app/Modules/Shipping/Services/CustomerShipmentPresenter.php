<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Commerce\Models\Order;
use App\Modules\Shipping\Models\Shipment;

/**
 * The only place shipping records are turned into customer-facing data.
 * It lists exactly the fields a customer may see: courier, tracking and progress.
 * Costs, quotations, agents, weights and internal notes are never included.
 */
class CustomerShipmentPresenter
{
    private const CUSTOMER_STATUS = [
        'booked' => 'shipped',
        'in_transit' => 'in_transit',
        'delivered' => 'delivered',
        'closed' => 'delivered',
    ];

    /**
     * @return list<array{carrier: ?string, tracking_number: string, tracking_url: ?string, status: string, shipped_at: ?string, delivered_at: ?string}>
     */
    public static function forOrder(Order $order): array
    {
        return Shipment::query()
            ->where('order_id', $order->id)
            ->where('workspace_id', $order->workspace_id)
            ->whereNotNull('tracking_number')
            ->whereIn('status', array_keys(self::CUSTOMER_STATUS))
            ->orderBy('id')
            ->get()
            ->map(fn (Shipment $shipment): array => [
                'carrier' => $shipment->carrierLabel(),
                'tracking_number' => $shipment->tracking_number,
                'tracking_url' => $shipment->tracking_url,
                'status' => self::CUSTOMER_STATUS[$shipment->status],
                'shipped_at' => $shipment->shipped_at?->toIso8601String(),
                'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
