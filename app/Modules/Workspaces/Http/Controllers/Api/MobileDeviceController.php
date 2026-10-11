<?php

namespace App\Modules\Workspaces\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\NotificationTemplates\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096'], 'platform' => ['required', 'in:android,ios']]);
        DeviceToken::query()->where('token', $data['token'])->delete();
        $request->user()->addDeviceToken($data['token'], $data['platform']);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        $request->user()->removeDeviceToken($data['token']);

        return response()->json(['success' => true]);
    }
}
