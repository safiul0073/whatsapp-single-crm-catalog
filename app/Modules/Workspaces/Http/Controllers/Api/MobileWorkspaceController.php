<?php

namespace App\Modules\Workspaces\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileWorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspaces = Workspace::query()->where('status', 'active')
            ->where(function ($query) use ($request): void {
                $query->where('owner_id', $request->user()->id)
                    ->orWhereHas('members', fn ($members) => $members->where('users.id', $request->user()->id)
                        ->where('workspace_members.status', WorkspaceMemberStatus::Active->value));
            })->orderBy('name')->get(['id', 'name', 'slug', 'owner_id']);

        return response()->json(['data' => $workspaces]);
    }
}
