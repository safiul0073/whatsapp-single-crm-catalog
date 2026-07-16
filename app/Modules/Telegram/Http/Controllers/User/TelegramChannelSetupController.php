<?php

namespace App\Modules\Telegram\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelAccountSetupService;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Telegram\Http\Requests\ConnectTelegramChannelRequest;
use App\Modules\Telegram\Services\TelegramBotProvider;
use App\Modules\Telegram\Services\TelegramInviteService;
use App\Modules\Telegram\Services\TelegramOptInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TelegramChannelSetupController extends Controller
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ChannelAccountSetupService $setup,
        protected ChannelManager $channels,
    ) {}

    public function index(Request $request, TelegramOptInService $optIns): View
    {
        $workspace = $this->workspaces->current($request->user());
        $channel = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'telegram')
            ->first();

        return view('telegram::user.setup', [
            'channel' => $channel,
            'telegramLinks' => $channel?->status === ChannelAccountStatus::Connected ? $optIns->publicLinksFor($channel) : null,
        ]);
    }

    public function store(ConnectTelegramChannelRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $existing = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'telegram')
            ->first();

        $credentials = $existing?->credentials ?? [];

        if (filled($request->input('access_token'))) {
            $credentials['access_token'] = $request->input('access_token');
        }

        $settings = array_merge(
            $existing?->settings ?? [],
            [
                'supports_channels' => $request->boolean('supports_channels'),
                'default_channel_username' => $request->input('default_channel_username'),
            ]
        );

        $channel = $this->setup->upsert($request->user(), 'telegram', [
            'id' => $existing?->id,
            'name' => $request->input('name'),
            'provider_account_id' => $request->input('provider_account_id'),
            'provider_display_id' => $request->input('provider_display_id'),
            'credentials' => $credentials,
            'settings' => $settings,
            'webhook_verify_token' => $existing?->webhook_verify_token ?: Str::random(40),
        ]);
        $result = $this->channels->testConnection($channel);
        $webhookResult = null;

        if ($result['ok'] ?? false) {
            $channel->refresh();
            $webhookResult = app(TelegramBotProvider::class)->setWebhook($channel);
        }

        $settings = array_merge($channel->settings ?? [], [
            'last_error' => $this->connectionErrorMessage($result, $webhookResult),
            'last_connection_tested_at' => now()->toISOString(),
            'telegram_bot_username' => $result['bot'] ?? null,
        ]);

        if ($webhookResult['ok'] ?? false) {
            $settings['last_webhook_set_at'] = now()->toISOString();
        }

        $channel->update([
            'status' => ($result['ok'] ?? false) ? ChannelAccountStatus::Connected->value : ChannelAccountStatus::Error->value,
            'connected_at' => ($result['ok'] ?? false) ? now() : null,
            'settings' => array_filter($settings, fn ($value) => $value !== null && $value !== ''),
        ]);

        if (! ($result['ok'] ?? false)) {
            return redirect()->route('user.telegram.index')->with('error', $result['error'] ?? 'Telegram connection test failed. Check the bot token.');
        }

        if (! ($webhookResult['ok'] ?? false)) {
            return redirect()->route('user.telegram.index')->with('error', 'Telegram bot connected, but webhook update failed: '.($webhookResult['message'] ?? 'Unknown webhook error.'));
        }

        return redirect()->route('user.telegram.index')->with('status', 'Telegram bot connected and webhook updated.');
    }

    public function update(ConnectTelegramChannelRequest $request, ChannelAccount $channel): RedirectResponse
    {
        return $this->store($request);
    }

    protected function connectionErrorMessage(array $connectionResult, ?array $webhookResult): ?string
    {
        if (! ($connectionResult['ok'] ?? false)) {
            return $connectionResult['error'] ?? 'Telegram connection test failed.';
        }

        if (! ($webhookResult['ok'] ?? false)) {
            return $webhookResult['message'] ?? 'Telegram webhook update failed.';
        }

        return null;
    }

    public function destroy(ChannelAccount $channel): RedirectResponse
    {
        $workspace = $this->workspaces->current(auth()->user());

        if ($channel->workspace_id !== $workspace->id || $channel->provider !== 'telegram') {
            abort(403);
        }

        $channel->update(['status' => ChannelAccountStatus::Disconnected->value]);

        return redirect()->route('user.telegram.index')->with('status', 'Telegram channel disconnected.');
    }

    public function test(ChannelAccount $channel)
    {
        $workspace = $this->workspaces->current(auth()->user());

        if ($channel->workspace_id !== $workspace->id || $channel->provider !== 'telegram') {
            abort(403);
        }

        $result = $this->channels->testConnection($channel);

        return back()->with($result['ok'] ? 'status' : 'error', $result['ok'] ? 'Connection successful.' : ($result['error'] ?? 'Connection failed.'));
    }

    public function setWebhook(ChannelAccount $channel)
    {
        $workspace = $this->workspaces->current(auth()->user());

        if ($channel->workspace_id !== $workspace->id || $channel->provider !== 'telegram') {
            abort(403);
        }

        $result = app(TelegramBotProvider::class)->setWebhook($channel);

        return back()->with($result['ok'] ? 'status' : 'error', $result['message'] ?? 'Webhook updated.');
    }

    public function optInLink(Request $request, Contact $contact, TelegramOptInService $optIns): JsonResponse
    {
        $workspace = $this->workspaces->current($request->user());

        abort_unless((int) $contact->workspace_id === (int) $workspace->id, 403);

        $link = $optIns->linkFor($contact);

        if (! $link) {
            return response()->json([
                'message' => 'Connect a Telegram bot before creating opt-in links.',
            ], 422);
        }

        return response()->json($link);
    }

    public function sendInvite(Request $request, Contact $contact, TelegramInviteService $invites): JsonResponse
    {
        $workspace = $this->workspaces->current($request->user());

        abort_unless((int) $contact->workspace_id === (int) $workspace->id, 403);

        $data = $request->validate([
            'channel' => ['required', 'string', 'in:copy,whatsapp,sms,email'],
            'message' => ['nullable', 'string', 'max:4000'],
        ]);

        $result = $invites->send($contact, $data['channel'], $data['message'] ?? null);

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }
}
