<?php

namespace App\Modules\Threads\Services;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ReadinessChecker;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ReadinessItem;
use App\Modules\MarketingChannels\Services\MetaErrorTranslator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;

/**
 * Threads tokens last 60 days, so the main thing to watch is access still working and the
 * login not expiring.
 */
class ThreadsReadinessChecker implements ReadinessChecker
{
    public function __construct(
        protected ThreadsClient $client,
        protected MetaErrorTranslator $errors,
    ) {}

    public function provider(): string
    {
        return 'threads';
    }

    public function check(ChannelAccount $account): array
    {
        $token = (string) $account->credential('access_token');

        if ($token === '') {
            return ['items' => [ReadinessItem::make('connection', __('Connect Threads'), 'blocked', __('The channel has no Threads access yet.'), [__('Use Connect with Threads.')], null, ['publish', 'receive'])]];
        }

        try {
            $response = $this->client->account((string) $account->provider_account_id, $token);
        } catch (\Throwable) {
            $response = null;
        }

        $items = [$this->access($response)];

        if ($expiry = $this->expiry($account)) {
            $items[] = $expiry;
        }

        return [
            'items' => $items,
            'facts' => array_values(array_filter([
                $account->provider_display_id ? ['label' => __('Account'), 'value' => (string) $account->provider_display_id] : null,
            ])),
        ];
    }

    protected function access(?Response $response): array
    {
        if ($response?->successful()) {
            return ReadinessItem::make('connection', __('Threads access working'), 'done', __('The app can publish and read replies on this account.'));
        }

        $error = $this->errors->translate($response?->json(), __('Threads could not be reached.'));

        return ReadinessItem::make('connection', __('Threads access working'), 'blocked', $error['message'], [__('Reconnect with Threads.')], null, ['publish', 'receive']);
    }

    protected function expiry(ChannelAccount $account): ?array
    {
        $expiresAt = data_get($account->settings, 'token_expires_at');

        if (blank($expiresAt)) {
            return null;
        }

        $date = Carbon::parse((string) $expiresAt);
        $soon = $date->lessThanOrEqualTo(now()->addDays(7));

        return ReadinessItem::make(
            'token_expiry',
            __('Threads login valid'),
            $soon ? 'action' : 'done',
            __('Threads logins expire after 60 days and must be renewed.'),
            $soon ? [__('Press Connect with Threads again to renew access. Nothing else changes.')] : [],
            null,
            ['publish', 'receive'],
            $soon ? __('Expires :date', ['date' => $date->toFormattedDateString()]) : __('Valid until :date', ['date' => $date->toFormattedDateString()]),
        );
    }
}
