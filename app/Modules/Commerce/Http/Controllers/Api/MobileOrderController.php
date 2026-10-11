<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\TransitionOrderRequest;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\UnifiedOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('commerce.view'), 403);
        $orders = Order::query()->where('workspace_id', $request->attributes->get('mobile_workspace_id'))
            ->with('contact:id,name,phone')->latest()->paginate(25);

        return response()->json($orders);
    }

    public function show(Request $request, string $order, UnifiedOrderService $orders): JsonResponse
    {
        abort_unless($request->user()->can('commerce.view'), 403);
        $model = Order::query()->where('workspace_id', $request->attributes->get('mobile_workspace_id'))->findOrFail($order);

        return response()->json(['data' => $orders->payload($model)]);
    }

    public function transition(TransitionOrderRequest $request, string $order, OrderWorkflowService $workflow, UnifiedOrderService $orders): JsonResponse
    {
        $model = Order::query()->where('workspace_id', $request->attributes->get('mobile_workspace_id'))->findOrFail($order);
        $updated = $workflow->transition($model, $request->validated('status'), $request->safe()->except('status'));

        return response()->json(['data' => $orders->payload($updated)]);
    }
}
