<?php

namespace App\Modules\Workspaces\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\NotificationTemplates\Models\DeviceToken;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096'], 'platform' => ['required', 'in:android,ios'], 'workspace_id' => ['nullable', 'integer']]);
        $workspaceId = isset($data['workspace_id']) ? (int) $data['workspace_id'] : null;
        abort_if($workspaceId && ! $this->belongsTo($request, $workspaceId), 403);
        DeviceToken::query()->where('token', $data['token'])->delete();
        $request->user()->addDeviceToken($data['token'], $data['platform'], $workspaceId);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        $request->user()->removeDeviceToken($data['token']);

        return response()->json(['success' => true]);
    }

    private function belongsTo(Request $request, int $workspaceId): bool
    {
        return Workspace::query()->whereKey($workspaceId)
            ->where(fn ($query) => $query->where('owner_id', $request->user()->id)
                ->orWhereHas('members', fn ($members) => $members->where('users.id', $request->user()->id)
                    ->where('workspace_members.status', WorkspaceMemberStatus::Active->value)))
            ->exists();
    }
}
