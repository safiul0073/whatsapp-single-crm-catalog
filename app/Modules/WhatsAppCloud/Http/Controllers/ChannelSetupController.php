<?php

namespace App\Modules\WhatsAppCloud\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ChannelReadinessService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\WhatsAppCloud\Http\Requests\ConnectEmbeddedWhatsAppChannelRequest;
use App\Modules\WhatsAppCloud\Http\Requests\ConnectGenericChannelRequest;
use App\Modules\WhatsAppCloud\Services\ChannelSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelSetupController extends Controller
{
    public function index(Request $request, ChannelSetupService $service): View
    {
        return view('whatsapp-cloud::user.channel-setup', $service->pageData($request->user()));
    }

    public function storeGeneric(ConnectGenericChannelRequest $request, ChannelSetupService $service): RedirectResponse
    {
        $channel = $service->connectGeneric($request->user(), $request->validated());
        $lastError = data_get($channel->settings, 'last_error');
        $hasError = $channel->status->value === 'error' || filled($lastError);

        return back()->with($hasError ? 'error' : 'status', $hasError
            ? "{$channel->name} was saved, but setup did not finish. ".($lastError ?: 'Check the channel warning.')
            : "{$channel->name} connected.");
    }

    public function embedded(ConnectEmbeddedWhatsAppChannelRequest $request, ChannelSetupService $service, ChannelReadinessService $readiness): RedirectResponse
    {
        $result = $service->connectFromEmbeddedSignup($request->user(), $request->validated());
        $channel = $result['channel'];
        $report = $readiness->check($channel);
        $failed = $channel->fresh()?->status?->value === 'error';

        return redirect()->route('user.whatsapp-cloud.channel-setup')->with($failed ? 'error' : 'status', $failed
            ? __('WhatsApp was saved, but setup did not finish: :error', ['error' => data_get($channel->fresh()?->settings, 'last_error', __('see the checklist below.'))])
            : trim(__('WhatsApp connected. Synced :numbers phone number(s) and :templates template(s).', ['numbers' => $result['phone_numbers'], 'templates' => $result['templates']]).' '.(($report['status'] ?? 'ready') === 'ready' ? '' : __('A few steps are left before you can send messages. See the checklist below.'))));
    }

    public function sync(Request $request, ChannelSetupService $service, ChannelReadinessService $readiness): RedirectResponse
    {
        $result = $service->sync($request->user());
        $channel = $service->pageData($request->user())['channel'];

        if ($channel) {
            $readiness->check($channel);
        }

        return back()->with($result['ok'] ? 'status' : 'error', $result['ok']
            ? "Synced {$result['phone_numbers']} phone number(s) and {$result['synced']} template(s) from Meta."
            : ($result['error'] ?? 'Meta sync failed. Check credentials.'));
    }

    public function test(Request $request, ChannelAccount $channel, WorkspaceResolver $workspaces, ChannelManager $channels): RedirectResponse
    {
        $workspace = $workspaces->current($request->user());

        abort_unless($channel->workspace_id === $workspace->id, 404);

        if (blank($channel->credential('access_token')) || $channel->status?->value === 'disconnected') {
            return back()->with('error', __('Channel is disconnected. Reconnect with Meta or update credentials first.'));
        }

        $result = $channels->testConnection($channel);

        return back()->with($result['ok'] ? 'status' : 'error', $result['ok']
            ? 'Connection successful.'
            : ($result['error'] ?? 'Connection failed.'));
    }

    public function disconnect(Request $request, ChannelSetupService $service, ?ChannelAccount $channel = null): RedirectResponse
    {
        $service->disconnect($request->user(), $channel);

        return back()->with('status', 'WhatsApp channel disconnected.');
    }

    public function destroy(Request $request, ChannelSetupService $service, ?ChannelAccount $channel = null): RedirectResponse
    {
        $service->destroy($request->user(), $channel);

        return back()->with('status', 'WhatsApp channel removed.');
    }
}
