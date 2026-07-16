<?php

namespace App\Modules\Sms\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelAccountSetupService;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Sms\Http\Requests\ConnectSmsChannelRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsChannelSetupController extends Controller
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ChannelAccountSetupService $setup,
        protected ChannelManager $channels,
    ) {}

    public function index(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());

        return view('sms::user.setup', [
            'channel' => ChannelAccount::query()
                ->where('workspace_id', $workspace->id)
                ->where('provider', 'sms')
                ->first(),
            'providers' => config('sms.providers', []),
            'defaultProvider' => config('sms.default_provider', 'log'),
        ]);
    }

    public function store(ConnectSmsChannelRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $existing = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'sms')
            ->first();

        $credentials = $this->credentialsFor($request, $existing);

        $channel = $this->setup->upsert($request->user(), 'sms', [
            'name' => $request->input('name'),
            'provider_display_id' => $request->input('provider_display_id'),
            'credentials' => array_filter($credentials, fn ($value) => $value !== null),
        ], $existing);

        $result = $this->channels->testConnection($channel);
        $this->updateConnectionStatus($channel, $result);

        return redirect()->route('user.sms.index')->with(($result['ok'] ?? false) ? 'status' : 'error', ($result['ok'] ?? false)
            ? 'SMS channel connected.'
            : ($result['error'] ?? 'SMS connection test failed.'));
    }

    public function update(ConnectSmsChannelRequest $request, ChannelAccount $channel): RedirectResponse
    {
        return $this->store($request);
    }

    protected function credentialsFor(ConnectSmsChannelRequest $request, ?ChannelAccount $existing): array
    {
        $provider = (string) $request->input('sms_provider', 'log');
        $providerConfig = config("sms.providers.{$provider}", []);
        $fields = $providerConfig['fields'] ?? [];
        $previous = $existing?->credentials ?? [];
        $sameProvider = ($previous['sms_provider'] ?? null) === $provider;

        $credentials = [
            'sms_provider' => $provider,
            'sms_from_number' => $request->input('provider_display_id'),
        ];

        foreach ($fields as $name => $field) {
            $value = $request->input($name);

            if (($field['secret'] ?? false) && blank($value) && $sameProvider) {
                $value = $previous[$name] ?? null;
            }

            if (($value === null || $value === '') && array_key_exists('default', $field)) {
                $value = $field['default'];
            }

            $credentials[$name] = $value;
        }

        return array_filter($credentials, fn ($value) => $value !== null && $value !== '');
    }

    public function destroy(ChannelAccount $channel): RedirectResponse
    {
        $workspace = $this->workspaces->current(auth()->user());

        if ($channel->workspace_id !== $workspace->id || $channel->provider !== 'sms') {
            abort(403);
        }

        $channel->update(['status' => ChannelAccountStatus::Disconnected->value]);

        return redirect()->route('user.sms.index')->with('status', 'SMS channel disconnected.');
    }

    public function test(ChannelAccount $channel)
    {
        $workspace = $this->workspaces->current(auth()->user());

        if ($channel->workspace_id !== $workspace->id || $channel->provider !== 'sms') {
            abort(403);
        }

        $result = $this->channels->testConnection($channel);

        return back()->with($result['ok'] ? 'status' : 'error', $result['ok'] ? 'Connection successful.' : ($result['error'] ?? 'Connection failed.'));
    }

    protected function updateConnectionStatus(ChannelAccount $channel, array $result): void
    {
        $connected = (bool) ($result['ok'] ?? false);

        $channel->update([
            'status' => $connected ? ChannelAccountStatus::Connected->value : ChannelAccountStatus::Error->value,
            'connected_at' => $connected ? now() : null,
            'settings' => array_filter(array_merge($channel->settings ?? [], [
                'last_error' => $connected ? null : ($result['error'] ?? 'SMS connection test failed.'),
                'last_connection_tested_at' => now()->toISOString(),
            ]), fn ($value) => $value !== null && $value !== ''),
        ]);
    }
}
