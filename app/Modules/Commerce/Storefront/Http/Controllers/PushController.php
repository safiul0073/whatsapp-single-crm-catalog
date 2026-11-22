<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\MobileCustomerDevice;
use App\Modules\Commerce\Storefront\Http\Requests\PushDeviceRequest;
use App\Modules\Commerce\Storefront\Services\StorePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function __construct(private StorePushService $push) {}

    /**
     * Public Firebase identifiers the store's app initializes with.
     */
    public function config(Request $request): JsonResponse
    {
        $config = $this->push->clientConfig((int) $request->attributes->get('store_workspace')->id);

        return response()->json(['data' => $config === null ? ['enabled' => false] : ['enabled' => true, ...$config]]);
    }

    public function register(PushDeviceRequest $request): JsonResponse
    {
        $customer = $request->attributes->get('store_customer');
        $token = (string) $request->validated('token');
        MobileCustomerDevice::query()->where('token', $token)->delete();
        MobileCustomerDevice::query()->create([
            'workspace_id' => $customer->workspace_id,
            'customer_reference' => 'storefront:'.$customer->id,
            'token' => $token,
            'platform' => (string) $request->validated('platform'),
        ]);

        return response()->json(['data' => ['registered' => true]]);
    }

    public function unregister(PushDeviceRequest $request): JsonResponse
    {
        $customer = $request->attributes->get('store_customer');
        MobileCustomerDevice::query()->where('workspace_id', $customer->workspace_id)
            ->where('customer_reference', 'storefront:'.$customer->id)
            ->where('token', (string) $request->validated('token'))
            ->delete();

        return response()->json(['data' => ['registered' => false]]);
    }
}
