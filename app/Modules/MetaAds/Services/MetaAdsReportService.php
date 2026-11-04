<?php

namespace App\Modules\MetaAds\Services;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaAds\Models\MetaAdReferral;
use App\Modules\MetaSocial\Models\MetaBusinessConnection;
use App\Modules\MetaSocial\Services\MetaBusinessConnectService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Meta Ads results for the ad accounts chosen on the Meta Business page, next to what those ads
 * produced here: chats started from an ad and the orders those contacts placed afterwards.
 */
class MetaAdsReportService
{
    public const PERIODS = ['7d' => 7, '30d' => 30, '90d' => 90];

    protected const MESSAGING_ACTIONS = ['onsite_conversion.messaging_conversation_started_7d'];

    protected const PURCHASE_ACTIONS = ['omni_purchase', 'purchase', 'offsite_conversion.fb_pixel_purchase', 'onsite_conversion.purchase'];

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected MetaBusinessConnectService $business,
        protected MetaAdsClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(?User $user, string $period): array
    {
        $workspace = $this->workspaces->current($user);
        $days = self::PERIODS[$period] ?? 30;
        $until = CarbonImmutable::today();
        $since = $until->subDays($days - 1);
        $connection = $this->business->connection($user);

        return [
            'period' => $period,
            'since' => $since,
            'until' => $until,
            'connected' => $connection !== null,
            'accounts' => $connection ? $this->accounts($connection, $since, $until) : [],
            'attribution' => $this->attribution((int) $workspace->id, $since, $until),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function accounts(MetaBusinessConnection $connection, CarbonImmutable $since, CarbonImmutable $until): array
    {
        $token = $connection->accessToken();
        $names = collect($connection->assetsOfType('ad_accounts'))->keyBy('id');
        $range = ['since' => $since->toDateString(), 'until' => $until->toDateString()];

        return collect($connection->selectedOfType('ad_accounts'))
            ->filter(fn (string $id): bool => $names->has($id))
            ->map(function (string $id) use ($token, $names, $range): array {
                $insights = $token ? Cache::remember(
                    'meta-ads:'.sha1($id.'|'.json_encode($range).'|'.$token),
                    now()->addMinutes(15),
                    fn (): array => $this->fetchInsights($id, $token, $range)
                ) : ['error' => __('Reconnect your Meta Business to load ad results.')];

                return [
                    'id' => $id,
                    'name' => $names->get($id)['name'] ?? $id,
                    'currency' => $insights['summary']['currency'] ?? ($names->get($id)['currency'] ?? ''),
                    'summary' => $insights['summary'] ?? null,
                    'campaigns' => $insights['campaigns'] ?? [],
                    'error' => $insights['error'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{since: string, until: string}  $range
     * @return array<string, mixed>
     */
    protected function fetchInsights(string $adAccountId, string $token, array $range): array
    {
        try {
            $account = $this->client->insights($adAccountId, $token, $range);
            $campaigns = $this->client->insights($adAccountId, $token, $range, 'campaign');
        } catch (\Throwable) {
            return ['error' => __('Meta could not be reached. Try again shortly.')];
        }

        if (! $account->successful()) {
            return ['error' => (string) data_get($account->json(), 'error.message', __('Meta did not return ad results. Check that ads_read was granted.'))];
        }

        return [
            'summary' => $this->metrics($account->json('data.0') ?? []),
            'campaigns' => collect($campaigns->successful() ? ($campaigns->json('data') ?? []) : [])
                ->map(fn (array $row): array => ['id' => (string) ($row['campaign_id'] ?? ''), 'name' => (string) ($row['campaign_name'] ?? '')] + $this->metrics($row))
                ->sortByDesc(fn (array $row): int => $row['spend_minor'])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function metrics(array $row): array
    {
        $spend = $this->decimal($row['spend'] ?? '0');

        return [
            'currency' => (string) ($row['account_currency'] ?? ''),
            'spend' => $spend,
            'spend_minor' => OrderMoney::minor($spend),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'conversations' => (int) $this->actionValue($row['actions'] ?? [], self::MESSAGING_ACTIONS),
            'purchases' => (int) $this->actionValue($row['actions'] ?? [], self::PURCHASE_ACTIONS),
            'purchase_value' => $this->decimal($this->actionValue($row['action_values'] ?? [], self::PURCHASE_ACTIONS)),
            'roas' => filled(data_get($row, 'purchase_roas.0.value')) ? $this->decimal((string) data_get($row, 'purchase_roas.0.value')) : null,
        ];
    }

    /**
     * Meta repeats purchases under several action types; take the first one present, never the sum.
     *
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, string>  $types
     */
    protected function actionValue(array $actions, array $types): string
    {
        $byType = collect($actions)->keyBy('action_type');

        foreach ($types as $type) {
            if ($byType->has($type)) {
                return (string) ($byType->get($type)['value'] ?? '0');
            }
        }

        return '0';
    }

    /**
     * @return array<string, mixed>
     */
    protected function attribution(int $workspaceId, CarbonImmutable $since, CarbonImmutable $until): array
    {
        $referrals = MetaAdReferral::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('referred_at', [$since->startOfDay(), $until->endOfDay()])
            ->orderBy('referred_at')
            ->get();

        $firstReferralByContact = $referrals->whereNotNull('contact_id')->groupBy('contact_id')->map(fn (Collection $rows) => $rows->first()->referred_at);
        $orders = $firstReferralByContact->isEmpty() ? collect() : Order::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('contact_id', $firstReferralByContact->keys())
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '<=', $until->endOfDay())
            ->get(['id', 'contact_id', 'total', 'currency', 'created_at'])
            ->filter(fn (Order $order): bool => $order->created_at >= $firstReferralByContact->get($order->contact_id));

        $ordersByContact = $orders->groupBy('contact_id');

        return [
            'conversations' => $referrals->unique(fn (MetaAdReferral $referral): string => (string) ($referral->conversation_id ?? $referral->id))->count(),
            'orders' => $orders->count(),
            'revenue' => $this->revenueByCurrency($orders),
            'ads' => $referrals->groupBy(fn (MetaAdReferral $referral): string => $referral->ad_id ?? 'unknown')
                ->map(function (Collection $rows, string $adId) use ($ordersByContact): array {
                    $adOrders = $rows->pluck('contact_id')->filter()->unique()->flatMap(fn ($contactId) => $ordersByContact->get($contactId, collect()));

                    return [
                        'ad_id' => $adId === 'unknown' ? null : $adId,
                        'headline' => $rows->pluck('headline')->filter()->first(),
                        'provider' => $rows->first()->provider,
                        'conversations' => $rows->pluck('conversation_id')->filter()->unique()->count(),
                        'orders' => $adOrders->count(),
                        'revenue' => $this->revenueByCurrency($adOrders),
                    ];
                })
                ->sortByDesc('conversations')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<string, string>
     */
    protected function revenueByCurrency(Collection $orders): array
    {
        return $orders->groupBy(fn (Order $order): string => (string) ($order->currency ?: ''))
            ->map(fn (Collection $rows): string => OrderMoney::decimal($rows->sum(fn (Order $order): int => OrderMoney::minor($this->decimal((string) $order->total)))))
            ->all();
    }

    /**
     * Normalises Meta and database amounts to a plain non-negative decimal string for OrderMoney.
     */
    protected function decimal(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{1,10}(?:\.\d+)?$/', $value) === 1 ? $value : '0';
    }
}
