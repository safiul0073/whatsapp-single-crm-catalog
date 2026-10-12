<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\MobileCustomerDevice;
use App\Modules\Commerce\Models\WhatsAppCustomerRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_reference' => ['required', 'string', 'max:150'], 'token' => ['required', 'string', 'max:4096'], 'platform' => ['required', 'in:android,ios']]);
        $workspaceId = $request->attributes->get('store_workspace')->id;
        abort_unless(WhatsAppCustomerRegistration::query()->where('workspace_id', $workspaceId)->where('customer_reference', $data['customer_reference'])->exists(), 404);
        MobileCustomerDevice::query()->where('token', $data['token'])->delete();
        MobileCustomerDevice::query()->create($data + ['workspace_id' => $workspaceId]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_reference' => ['required', 'string', 'max:150'], 'token' => ['required', 'string', 'max:4096']]);
        MobileCustomerDevice::query()->where('workspace_id', $request->attributes->get('store_workspace')->id)
            ->where('customer_reference', $data['customer_reference'])->where('token', $data['token'])->delete();

        return response()->json(['success' => true]);
    }
}
