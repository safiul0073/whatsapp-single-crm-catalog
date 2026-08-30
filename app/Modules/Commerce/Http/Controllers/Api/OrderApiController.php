<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderApiController extends Controller
{
    public function trackOrder(string $trackingNumber): JsonResponse
    {
        $order = Order::query()
            ->with(['items.variant.product.primaryMedia'])
            ->where('tracking_number', $trackingNumber)
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'No order found with the provided tracking number.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => [
                'number' => $order->number,
                'status' => $order->status,
                'currency' => $order->currency,
                'total' => $order->total,
                'tracking_number' => $order->tracking_number,
                'tracking_url' => $order->tracking_url,
                'shipped_at' => $order->shipped_at,
                'created_at' => $order->created_at,
                'items' => $order->items->map(function ($item) {
                    return [
                        'name' => $item->variant?->product?->name ?? 'Product',
                        'quantity' => $item->quantity,
                        'price' => $item->unit_price,
                        'image' => $item->variant?->product?->primaryMedia?->url,
                    ];
                }),
            ]
        ]);
    }
}
