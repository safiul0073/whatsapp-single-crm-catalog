<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderApiController extends Controller
{
    public function trackOrder(string $trackingNumber): JsonResponse
    {
        $orders = Order::query()
            ->where(fn ($query) => $query->where('tracking_number', trim($trackingNumber))->orWhere('tracking_code', trim($trackingNumber)))
            ->limit(2)
            ->get();

        if (trim($trackingNumber) === '' || strlen($trackingNumber) > 150 || $orders->count() !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'No order found with the provided tracking number.',
            ], 404);
        }

        $order = $orders->first();

        return response()->json([
            'success' => true,
            'order' => [
                'number' => $order->number,
                'status' => $order->status,
                'status_label' => $order->status === 'completed' ? 'Delivered' : str($order->status)->replace('_', ' ')->title()->toString(),
                'tracking_number' => $order->tracking_number ?? $order->tracking_code,
                'tracking_url' => $order->tracking_url,
                'shipped_at' => $order->shipped_at,
                'created_at' => $order->created_at,
                'timeline' => $order->trackingTimeline(),
            ],
        ]);
    }
}
