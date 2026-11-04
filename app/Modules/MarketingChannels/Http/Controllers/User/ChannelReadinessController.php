<?php

namespace App\Modules\MarketingChannels\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelHubService;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ChannelReadinessService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChannelReadinessController extends Controller
{
    public function __construct(
        protected ChannelReadinessService $readiness,
        protected WorkspaceResolver $workspaces,
    ) {}

    public function check(Request $request, ChannelAccount $channel): RedirectResponse
    {
        abort_unless($channel->workspace_id === $this->workspaces->current($request->user())->id, 404);

        $permission = ChannelHubService::PERMISSIONS[$channel->provider] ?? 'channels.manage';
        abort_unless(Gate::allows($permission), 403);
        abort_unless($this->readiness->supports($channel->provider), 404);

        $report = $this->readiness->check($channel);

        return back()->with(($report['status'] ?? 'action') === 'ready' ? 'status' : 'error', __(':channel: :headline', [
            'channel' => $channel->name,
            'headline' => $report['headline'] ?? __('Checks finished.'),
        ]));
    }
}
