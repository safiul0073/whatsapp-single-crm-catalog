<?php

namespace App\Modules\Workspaces\Http\Middleware;

use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveMobileWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = Workspace::query()->whereKey($request->route('workspace'))
            ->where('status', 'active')
            ->where(function ($query) use ($request): void {
                $query->where('owner_id', $request->user()->id)
                    ->orWhereHas('members', fn ($members) => $members->where('users.id', $request->user()->id)
                        ->where('workspace_members.status', WorkspaceMemberStatus::Active->value));
            })->firstOrFail();

        $request->attributes->set('mobile_workspace_id', $workspace->id);

        return $next($request);
    }
}
