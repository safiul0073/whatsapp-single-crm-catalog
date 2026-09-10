<?php

namespace App\Modules\MarketingChannels\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Builds the per-provider cards for the channels hub and owns the status → badge mapping
 * shared by every channel setup page.
 */
class ChannelHubService
{
    public const SETUP_ROUTES = [
        'whatsapp' => 'user.whatsapp-cloud.channel-setup',
        'telegram' => 'user.telegram.index',
        'email' => 'user.email.index',
        'sms' => 'user.sms.index',
        'messenger' => 'user.meta-social.setup',
        'instagram' => 'user.meta-social.setup',
        'threads' => 'user.meta-social.setup',
    ];

    public const PERMISSIONS = [
        'whatsapp' => 'channels.manage',
        'telegram' => 'telegram.manage',
        'email' => 'email.manage',
        'sms' => 'sms.manage',
        'messenger' => 'meta-social.manage',
        'instagram' => 'meta-social.manage',
        'threads' => 'threads.manage',
    ];

    public const SECTION_ANCHORS = [
        'messenger' => 'messenger',
        'instagram' => 'instagram',
        'threads' => 'threads',
    ];

    public function __construct(protected WorkspaceResolver $workspaces) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cards(?User $user): array
    {
        $workspace = $this->workspaces->current($user);
        $accountsByProvider = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->get()
            ->groupBy('provider');

        return collect(config('marketing-channels.providers', []))
            ->map(fn (array $provider, string $key): array => $this->card($key, $provider, $accountsByProvider->get($key, collect())))
            ->values()
            ->all();
    }

    /**
     * @return array{label: string, variant: string, value: string}
     */
    public function statusFor(?ChannelAccount $account): array
    {
        return match ($account?->status) {
            ChannelAccountStatus::Connected => ['label' => 'Connected', 'variant' => 'success', 'value' => 'connected'],
            ChannelAccountStatus::Error => ['label' => 'Error', 'variant' => 'error', 'value' => 'error'],
            ChannelAccountStatus::Disconnected => ['label' => 'Disconnected', 'variant' => 'neutral', 'value' => 'disconnected'],
            ChannelAccountStatus::Suspended => ['label' => 'Suspended', 'variant' => 'warning', 'value' => 'suspended'],
            ChannelAccountStatus::Draft => ['label' => 'Pending', 'variant' => 'warning', 'value' => 'draft'],
            default => ['label' => 'Not connected', 'variant' => 'neutral', 'value' => 'none'],
        };
    }

    public function setupUrl(string $provider): ?string
    {
        $routeName = self::SETUP_ROUTES[$provider] ?? null;

        if (! $routeName || ! Route::has($routeName)) {
            return null;
        }

        $anchor = self::SECTION_ANCHORS[$provider] ?? null;

        return route($routeName).($anchor ? '#'.$anchor : '');
    }

    /**
     * @param  Collection<int, ChannelAccount>  $accounts
     * @return array<string, mixed>
     */
    protected function card(string $key, array $provider, Collection $accounts): array
    {
        $isInternal = ($provider['connect_mode'] ?? null) === 'internal';
        $primary = $this->primaryAccount($accounts);

        return [
            'key' => $key,
            'label' => $provider['label'],
            'title' => $provider['title'],
            'icon' => $provider['icon'],
            'description' => $provider['description'],
            'capabilities' => $provider['capabilities'] ?? [],
            'internal' => $isInternal,
            'accounts' => $accounts,
            'account' => $primary,
            'status' => $this->statusFor($primary),
            'connected_count' => $accounts->filter(fn (ChannelAccount $account): bool => $account->status === ChannelAccountStatus::Connected)->count(),
            'setup_url' => $isInternal ? null : $this->setupUrl($key),
            'permission' => self::PERMISSIONS[$key] ?? null,
        ];
    }

    /**
     * @param  Collection<int, ChannelAccount>  $accounts
     */
    protected function primaryAccount(Collection $accounts): ?ChannelAccount
    {
        return $accounts->first(fn (ChannelAccount $account): bool => $account->status === ChannelAccountStatus::Connected)
            ?? $accounts->first(fn (ChannelAccount $account): bool => $account->status === ChannelAccountStatus::Error)
            ?? $accounts->first();
    }
}
