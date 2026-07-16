<?php

namespace App\Modules\Email\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Email\Http\Requests\ConnectEmailChannelRequest;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelAccountSetupService;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailChannelSetupController extends Controller
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ChannelAccountSetupService $setup,
        protected ChannelManager $channels,
    ) {}

    public function index(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());

        return view('email::user.setup', [
            'channel' => ChannelAccount::query()
                ->where('workspace_id', $workspace->id)
                ->where('provider', 'email')
                ->first(),
            'providers' => config('email.providers', []),
            'defaultProvider' => config('email.default_provider', 'log'),
        ]);
    }

    public function store(ConnectEmailChannelRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $existing = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'email')
            ->first();

        $credentials = $this->credentialsFor($request, $existing);

        $channel = $this->setup->upsert($request->user(), 'email', [
            'name' => $request->input('name'),
            'provider_display_id' => $request->input('provider_display_id'),
            'credentials' => array_filter($credentials, fn ($value) => $value !== null),
        ], $existing);

        $result = $this->channels->testConnection($channel);
        $this->updateConnectionStatus($channel, $result);

        return redirect()->route('user.email.index')->with(($result['ok'] ?? false) ? 'status' : 'error', ($result['ok'] ?? false)
            ? 'Email channel connected.'
            : ($result['error'] ?? 'Email connection test failed.'));
    }

    public function update(ConnectEmailChannelRequest $request, ChannelAccount $channel): RedirectResponse
    {
        return $this->store($request);
    }

    protected function credentialsFor(ConnectEmailChannelRequest $request, ?ChannelAccount $existing): array
    {
        $mailer = (string) $request->input('mail_mailer', 'log');
        $provider = config("email.providers.{$mailer}", []);
        $fields = $provider['fields'] ?? [];
        $previous = $existing?->credentials ?? [];
        $sameMailer = ($previous['mail_mailer'] ?? null) === $mailer;

        $credentials = [
            'mail_mailer' => $mailer,
            'mail_from_name' => $request->input('mail_from_name'),
        ];

        foreach ($fields as $name => $field) {
            $value = $request->input($name);

            if (($field['secret'] ?? false) && blank($value) && $sameMailer) {
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

        if ($channel->workspace_id !== $workspace->id || $channel->provider !== 'email') {
            abort(403);
        }

        $channel->update(['status' => ChannelAccountStatus::Disconnected->value]);

        return redirect()->route('user.email.index')->with('status', 'Email channel disconnected.');
    }

    public function test(ChannelAccount $channel)
    {
        $workspace = $this->workspaces->current(auth()->user());

        if ($channel->workspace_id !== $workspace->id || $channel->provider !== 'email') {
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
                'last_error' => $connected ? null : ($result['error'] ?? 'Email connection test failed.'),
                'last_connection_tested_at' => now()->toISOString(),
            ]), fn ($value) => $value !== null && $value !== ''),
        ]);
    }
}
