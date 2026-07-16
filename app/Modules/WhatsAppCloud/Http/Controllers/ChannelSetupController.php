<?php

namespace App\Modules\WhatsAppCloud\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\WhatsAppCloud\Http\Requests\ConnectEmbeddedWhatsAppChannelRequest;
use App\Modules\WhatsAppCloud\Http\Requests\ConnectGenericChannelRequest;
use App\Modules\WhatsAppCloud\Http\Requests\ConnectWhatsAppChannelRequest;
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

    public function store(ConnectWhatsAppChannelRequest $request, ChannelSetupService $service): RedirectResponse
    {
        $channel = $service->connect($request->user(), $request->validated());

        return back()->with('status', $channel->status->value === 'error'
            ? 'WhatsApp channel saved. Check the card warning before sending messages.'
            : 'WhatsApp channel connected.');
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

    public function embedded(ConnectEmbeddedWhatsAppChannelRequest $request, ChannelSetupService $service): RedirectResponse
    {
        $result = $service->connectFromEmbeddedSignup($request->user(), $request->validated());

        return back()->with('status', "Connected WhatsApp with Meta. Synced {$result['phone_numbers']} phone number(s) and {$result['templates']} template(s).");
    }

    public function sync(Request $request, ChannelSetupService $service): RedirectResponse
    {
        $result = $service->sync($request->user());

        return back()->with('status', $result['ok']
            ? "Synced {$result['phone_numbers']} phone number(s) and {$result['synced']} template(s) from Meta."
            : 'Meta sync failed. Check credentials.');
    }

    public function test(Request $request, ChannelAccount $channel, WorkspaceResolver $workspaces, ChannelManager $channels): RedirectResponse
    {
        $workspace = $workspaces->current($request->user());

        abort_unless($channel->workspace_id === $workspace->id, 404);

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
}
