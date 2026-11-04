<?php

namespace App\Modules\MarketingChannels\Services\ChannelReadiness;

use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Support\Facades\Log;

/**
 * Runs the provider's readiness checker and keeps the latest result on the channel
 * (settings.readiness) so pages render it without calling the provider again.
 */
class ChannelReadinessService
{
    /**
     * @var array<string, ReadinessChecker>
     */
    protected array $checkers = [];

    public function register(ReadinessChecker $checker): void
    {
        $this->checkers[$checker->provider()] = $checker;
    }

    public function supports(string $provider): bool
    {
        return isset($this->checkers[$provider]);
    }

    /**
     * Calls the provider and stores the result.
     *
     * @return array<string, mixed>|null
     */
    public function check(ChannelAccount $account): ?array
    {
        $checker = $this->checkers[$account->provider] ?? null;

        if (! $checker) {
            return null;
        }

        try {
            $result = $checker->check($account);
        } catch (\Throwable $exception) {
            Log::warning('Channel readiness check failed.', ['channel_account_id' => $account->id, 'error' => $exception->getMessage()]);

            $result = ['items' => [ReadinessItem::make(
                'check_failed',
                __('Could not run the checks'),
                'unknown',
                __('Meta did not answer while checking this channel.'),
                [__('Wait a minute and press Re-check.')],
            )]];
        }

        $report = $this->summarize($result['items'] ?? [], $result['facts'] ?? [], $result['notes'] ?? []);

        $settings = $account->fresh()?->settings ?? $account->settings ?? [];
        $settings['readiness'] = $report;
        $account->forceFill(['settings' => $settings])->saveQuietly();

        return $report;
    }

    /**
     * Re-checks every connected channel that has a checker, a chunk at a time.
     */
    public function checkConnected(): int
    {
        $checked = 0;

        ChannelAccount::query()
            ->whereIn('provider', array_keys($this->checkers))
            ->where('status', ChannelAccountStatus::Connected->value)
            ->chunkById(100, function ($accounts) use (&$checked): void {
                foreach ($accounts as $account) {
                    $this->check($account);
                    $checked++;
                }
            });

        return $checked;
    }

    /**
     * The stored result, or null when the channel was never checked.
     *
     * @return array<string, mixed>|null
     */
    public function report(ChannelAccount $account): ?array
    {
        $report = data_get($account->settings, 'readiness');

        return is_array($report) && isset($report['items']) ? $report : null;
    }

    /**
     * The stored result, checking first when there is none yet or it is older than $maxAgeMinutes.
     *
     * @return array<string, mixed>|null
     */
    public function fresh(ChannelAccount $account, int $maxAgeMinutes = 360): ?array
    {
        $report = $this->report($account);
        $checkedAt = isset($report['checked_at']) ? strtotime((string) $report['checked_at']) : false;

        if ($report && $checkedAt && $checkedAt > now()->subMinutes($maxAgeMinutes)->getTimestamp()) {
            return $report;
        }

        return $this->check($account) ?? $report;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array{label: string, value: string}>  $facts
     * @param  array<int, string>  $notes
     * @return array<string, mixed>
     */
    public function summarize(array $items, array $facts = [], array $notes = []): array
    {
        $open = collect($items)->whereIn('state', ['action', 'waiting', 'blocked', 'unknown']);
        $required = collect($items)->where('state', '!=', 'optional');
        $status = match (true) {
            $open->contains('state', 'blocked') => 'blocked',
            $open->contains('state', 'action') || $open->contains('state', 'unknown') => 'action',
            $open->contains('state', 'waiting') => 'waiting',
            default => 'ready',
        };
        $blocked = $open->pluck('blocks')->flatten()->unique()->values()->all();

        return [
            'status' => $status,
            'done' => $required->where('state', 'done')->count(),
            'total' => $required->count(),
            'open' => $open->count(),
            'blocked_features' => $blocked,
            'headline' => $this->headline($status, $blocked, $open->first()),
            'items' => array_values($items),
            'facts' => array_values($facts),
            'notes' => array_values($notes),
            'checked_at' => now()->toISOString(),
        ];
    }

    /**
     * @param  array<int, string>  $blocked
     * @param  array<string, mixed>|null  $firstOpen
     */
    protected function headline(string $status, array $blocked, ?array $firstOpen): string
    {
        if ($status === 'ready') {
            return __('Everything is set up. This channel can receive and send messages.');
        }

        $labels = [
            'receive' => __('receiving messages'),
            'send' => __('sending messages'),
            'templates' => __('template messages'),
            'campaigns' => __('campaigns'),
            'publish' => __('publishing'),
        ];
        $features = collect($blocked)->map(fn (string $feature): string => $labels[$feature] ?? $feature)->implode(', ');
        $next = $firstOpen['label'] ?? '';

        if ($features === '') {
            return __('Almost ready. Next: :next', ['next' => $next]);
        }

        return $status === 'waiting'
            ? __('Waiting for Meta before :features work. Next: :next', ['features' => $features, 'next' => $next])
            : __(':features will not work until this is done. Next: :next', ['features' => ucfirst($features), 'next' => $next]);
    }
}
