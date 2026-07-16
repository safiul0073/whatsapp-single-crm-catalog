<?php

namespace App\Modules\MarketingChannels\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Validation\ValidationException;

class ChannelAccountSetupService
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function upsert(?User $user, string $provider, array $data, ?ChannelAccount $channel = null): ChannelAccount
    {
        $workspace = $this->workspaces->current($user);

        if ($channel && $channel->workspace_id !== $workspace->id) {
            throw ValidationException::withMessages(['channel' => 'Channel does not belong to the current workspace.']);
        }

        $credentials = $data['credentials'] ?? [];
        $settings = $data['settings'] ?? [];

        $channel = ChannelAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'provider' => $provider,
                'id' => $channel?->id,
            ],
            [
                'name' => $data['name'],
                'status' => ChannelAccountStatus::Connected->value,
                'credentials' => $credentials,
                'settings' => $settings,
                'provider_account_id' => $data['provider_account_id'] ?? null,
                'provider_phone_id' => $data['provider_phone_id'] ?? null,
                'provider_display_id' => $data['provider_display_id'] ?? null,
                'webhook_verify_token' => $data['webhook_verify_token'] ?? null,
                'connected_at' => now(),
            ]
        );

        return $channel;
    }

    public function findForUser(?User $user, string $provider): ?ChannelAccount
    {
        $workspace = $this->workspaces->current($user);

        return ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', $provider)
            ->first();
    }

    public function listForUser(?User $user, ?string $provider = null)
    {
        $workspace = $this->workspaces->current($user);

        return ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->when($provider, fn ($query) => $query->where('provider', $provider))
            ->get();
    }
}
