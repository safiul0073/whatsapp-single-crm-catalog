<?php

namespace App\Modules\PlansSubscriptions\Services;

use App\Enums\NotificationTemplateSlug;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\PlansSubscriptions\Enums\SubscriptionEventType;
use App\Modules\PlansSubscriptions\Exceptions\SubscriptionLimitException;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Models\SubscriptionEvent;
use App\Modules\PlansSubscriptions\Notifications\SubscriptionUsageNotification;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Plan quotas. Plan limits follow these rules:
 *  - key missing or null => unlimited
 *  - 0                  => blocked
 *  - counted quotas reset each monthly usage period (also for yearly / lifetime plans)
 *  - gauges are counted live from their tables (contacts, numbers, chatbots, seats)
 */
class PlanLimitService
{
    public const PLATFORM_AI_CREDITS_LIMIT = 'max_ai_credits';

    public const ALERT_THRESHOLDS = [80, 100];

    /** Legacy / alternate key names used by callers => canonical key stored on plans. */
    public const ALIASES = [
        'max_messages_per_month' => 'messages_per_month',
        'max_contacts' => 'contacts',
        'max_campaigns_per_month' => 'campaigns_per_month',
        'max_whatsapp_numbers' => 'whatsapp_numbers',
        'max_chatbots' => 'chatbots',
        'max_team_members' => 'team_members',
        'team_seats' => 'team_members',
        'max_ai_tokens' => 'ai_tokens',
    ];

    /** Counted monthly quotas tracked in subscriptions.usage. */
    public const COUNTED = [
        'messages_per_month',
        'campaigns_per_month',
        'ai_tokens',
        'max_lead_generations_per_month',
        'max_ai_lead_results_per_month',
        'max_ai_credits',
    ];

    public const GAUGES = ['contacts', 'whatsapp_numbers', 'chatbots', 'team_members'];

    public function __construct(protected SubscriptionAccessService $access) {}

    public static function canonical(string $limit): string
    {
        return self::ALIASES[$limit] ?? $limit;
    }

    /**
     * Whether the workspace may use $amount more units of $limit.
     */
    public function allows(int $workspaceId, string $limit, int $amount = 1): bool
    {
        if (! $this->access->enforced()) {
            return true;
        }

        $subscription = $this->access->currentForWorkspace($workspaceId);

        if (! $subscription || ! $this->access->subscriptionIsUsable($subscription)) {
            return false;
        }

        $remaining = $this->remainingFor($subscription, self::canonical($limit));

        return $remaining === null || $remaining >= $amount;
    }

    /**
     * Whether a size (e.g. a campaign audience) fits inside the plan limit, without counting current usage.
     */
    public function withinLimit(int $workspaceId, string $limit, int $value): bool
    {
        if (! $this->access->enforced()) {
            return true;
        }

        $subscription = $this->access->currentForWorkspace($workspaceId);

        if (! $subscription || ! $this->access->subscriptionIsUsable($subscription)) {
            return false;
        }

        $maximum = $this->limitValue($subscription->plan, self::canonical($limit));

        return $maximum === null || $value <= $maximum;
    }

    /**
     * Throw a 403 when the workspace cannot use $amount more units of $limit.
     */
    public function ensure(int $workspaceId, string $limit, int $amount = 1, ?string $message = null): void
    {
        if ($this->allows($workspaceId, $limit, $amount)) {
            return;
        }

        if (! $this->access->canUseServices($workspaceId)) {
            throw new SubscriptionLimitException(__(SubscriptionAccessService::EXPIRED_MESSAGE));
        }

        throw new SubscriptionLimitException($message ?? __('Your plan limit for :limit has been reached. Please upgrade your plan.', [
            'limit' => self::label(self::canonical($limit)),
        ]));
    }

    /**
     * Record usage of a counted quota. Gauges are counted live, so consuming them is a no-op.
     */
    public function consume(int $workspaceId, string $limit, int $amount = 1): void
    {
        $key = self::canonical($limit);

        if ($amount <= 0 || ! in_array($key, self::COUNTED, true) || ! $this->access->enforced()) {
            return;
        }

        $alerts = DB::transaction(function () use ($workspaceId, $key, $amount): array {
            $subscription = Subscription::query()->with('plan')->where('workspace_id', $workspaceId)->lockForUpdate()->first();

            if (! $subscription) {
                return [];
            }

            $this->rollUsagePeriod($subscription);

            $usage = $subscription->usage ?? [];
            $before = (int) ($usage[$key] ?? 0);
            $after = $before + $amount;
            $usage[$key] = $after;

            $subscription->forceFill(['usage' => $usage])->save();

            return $this->crossedThresholds($subscription, $key, $before, $after);
        });

        foreach ($alerts as $alert) {
            $this->sendAlert($alert['subscription'], $key, $alert['threshold'], $alert['used'], $alert['limit']);
        }
    }

    /**
     * AI features answer with a 422 "plan" validation error when credits run out (403 when the plan expired).
     */
    public function ensurePlatformAiCredits(int $workspaceId, int $amount = 1): void
    {
        if ($this->allows($workspaceId, self::PLATFORM_AI_CREDITS_LIMIT, $amount)) {
            return;
        }

        if (! $this->access->canUseServices($workspaceId)) {
            throw new SubscriptionLimitException(__(SubscriptionAccessService::EXPIRED_MESSAGE));
        }

        throw ValidationException::withMessages([
            'plan' => __('Your platform AI credits are over. Please upgrade your plan or buy more credits.'),
        ]);
    }

    public function consumePlatformAiCredits(int $workspaceId, int $amount = 1): void
    {
        $this->consume($workspaceId, self::PLATFORM_AI_CREDITS_LIMIT, $amount);
    }

    public function featureEnabled(int $workspaceId, string $feature): bool
    {
        if (! $this->access->enforced()) {
            return true;
        }

        $subscription = $this->access->currentForWorkspace($workspaceId);

        if (! $subscription || ! $this->access->subscriptionIsUsable($subscription)) {
            return false;
        }

        return (bool) $subscription->plan?->limit($feature);
    }

    /**
     * Quota-only check: true when the workspace plan has a limit for $limit and it is used up.
     * Expiry is handled separately (middleware / canUseServices), so this never blocks on status.
     */
    public function reached(int $workspaceId, string $limit, int $amount = 1): bool
    {
        $subscription = $this->access->currentForWorkspace($workspaceId);
        $remaining = $subscription ? $this->remainingFor($subscription, self::canonical($limit)) : null;

        return $remaining !== null && $remaining < $amount;
    }

    /**
     * The plan's maximum for $limit, or null when unlimited.
     */
    public function limit(int $workspaceId, string $limit): ?int
    {
        return $this->limitValue($this->access->currentForWorkspace($workspaceId)?->plan, self::canonical($limit));
    }

    /**
     * Units left for $limit, or null when unlimited.
     */
    public function remaining(int $workspaceId, string $limit): ?int
    {
        if (! $this->access->enforced()) {
            return null;
        }

        $subscription = $this->access->currentForWorkspace($workspaceId);

        if (! $subscription) {
            return 0;
        }

        return $this->remainingFor($subscription, self::canonical($limit));
    }

    public function used(Subscription $subscription, string $key): int
    {
        $key = self::canonical($key);

        if (in_array($key, self::GAUGES, true)) {
            return $this->gaugeCount((int) $subscription->workspace_id, $key);
        }

        if ($this->usagePeriodIsStale($subscription)) {
            return 0;
        }

        return (int) data_get($subscription->usage ?? [], $key, 0);
    }

    /**
     * Usage meters for every numeric limit on the plan.
     *
     * @return array<int, array{key: string, label: string, used: int, maximum: int|null, percent: int, tone: string, period: string}>
     */
    public function usageSnapshot(Subscription $subscription, ?Plan $plan = null): array
    {
        $plan ??= $subscription->plan;
        $rows = [];

        foreach ([...self::GAUGES, ...self::COUNTED] as $key) {
            $maximum = $this->limitValue($plan, $key);

            if ($maximum === null && ! array_key_exists($key, $plan?->limits ?? [])) {
                continue;
            }

            $used = $this->used($subscription, $key);
            $percent = $maximum === null ? 0 : ($maximum > 0 ? min(100, (int) floor($used * 100 / $maximum)) : 100);

            $rows[] = [
                'key' => $key,
                'label' => self::label($key),
                'used' => $used,
                'maximum' => $maximum,
                'percent' => $percent,
                'tone' => $percent >= 100 ? 'error' : ($percent >= 80 ? 'warning' : 'primary'),
                'period' => in_array($key, self::GAUGES, true) ? 'total' : 'month',
            ];
        }

        return $rows;
    }

    public function resetUsage(Subscription $subscription): void
    {
        $subscription->forceFill([
            'usage' => [],
            'usage_period_start' => $this->currentPeriodStart($subscription),
        ])->save();
    }

    public function currentPeriodStart(Subscription $subscription): Carbon
    {
        $anchor = ($subscription->starts_at ?? $subscription->created_at ?? now())->copy();

        if ($anchor->isFuture()) {
            return $anchor;
        }

        $months = (int) floor($anchor->diffInMonths(now()));

        return $anchor->addMonthsNoOverflow($months);
    }

    public static function label(string $key): string
    {
        return match ($key) {
            'messages_per_month' => __('Messages / month'),
            'contacts' => __('Contacts'),
            'whatsapp_numbers' => __('WhatsApp numbers'),
            'ai_tokens' => __('AI tokens'),
            'campaigns_per_month' => __('Campaigns / month'),
            'chatbots' => __('Chatbots'),
            'team_members' => __('Team seats'),
            'max_lead_generations_per_month' => __('Lead generations / month'),
            'max_ai_lead_results_per_month' => __('Generated leads / month'),
            'max_ai_credits' => __('Platform AI credits'),
            default => __(str($key)->replace('_', ' ')->title()->toString()),
        };
    }

    protected function remainingFor(Subscription $subscription, string $key): ?int
    {
        $maximum = $this->limitValue($subscription->plan, $key);

        if ($maximum === null) {
            return null;
        }

        return max(0, $maximum - $this->used($subscription, $key));
    }

    protected function limitValue(?Plan $plan, string $key): ?int
    {
        $value = $plan?->limit($key);

        if ($value === null || $value === '' || is_bool($value) || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    protected function gaugeCount(int $workspaceId, string $key): int
    {
        return match ($key) {
            'contacts' => Contact::query()->where('workspace_id', $workspaceId)->count(),
            'whatsapp_numbers' => ChannelAccount::query()->where('workspace_id', $workspaceId)->where('provider', 'whatsapp')->count(),
            'chatbots' => Chatbot::query()->where('workspace_id', $workspaceId)->count(),
            'team_members' => WorkspaceMember::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', '!=', WorkspaceMemberStatus::Suspended->value)
                ->count()
                + WorkspaceInvitation::query()
                    ->where('workspace_id', $workspaceId)
                    ->whereNull('accepted_at')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->count(),
            default => 0,
        };
    }

    protected function usagePeriodIsStale(Subscription $subscription): bool
    {
        return $subscription->usage_period_start !== null
            && $subscription->usage_period_start->lt($this->currentPeriodStart($subscription));
    }

    protected function rollUsagePeriod(Subscription $subscription): void
    {
        if ($subscription->usage_period_start === null) {
            $subscription->usage_period_start = $this->currentPeriodStart($subscription);

            return;
        }

        if ($this->usagePeriodIsStale($subscription)) {
            $subscription->usage = [];
            $subscription->usage_period_start = $this->currentPeriodStart($subscription);
        }
    }

    /**
     * @return array<int, array{subscription: Subscription, threshold: int, used: int, limit: int}>
     */
    protected function crossedThresholds(Subscription $subscription, string $key, int $before, int $after): array
    {
        $maximum = $this->limitValue($subscription->plan, $key);

        if (! $maximum) {
            return [];
        }

        $alerts = [];

        foreach (self::ALERT_THRESHOLDS as $threshold) {
            $line = (int) ceil($maximum * $threshold / 100);

            if ($before >= $line || $after < $line) {
                continue;
            }

            $period = $subscription->usage_period_start?->toDateString();
            $alreadySent = SubscriptionEvent::query()
                ->where('subscription_id', $subscription->id)
                ->where('type', SubscriptionEventType::LimitAlert->value)
                ->where('meta->key', $key)
                ->where('meta->threshold', $threshold)
                ->where('meta->period', $period)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            SubscriptionEvent::query()->create([
                'subscription_id' => $subscription->id,
                'type' => SubscriptionEventType::LimitAlert,
                'to_plan_id' => $subscription->plan_id,
                'meta' => ['key' => $key, 'threshold' => $threshold, 'period' => $period, 'used' => $after, 'limit' => $maximum],
            ]);

            $alerts[] = ['subscription' => $subscription, 'threshold' => $threshold, 'used' => $after, 'limit' => $maximum];
        }

        return $alerts;
    }

    protected function sendAlert(Subscription $subscription, string $key, int $threshold, int $used, int $limit): void
    {
        $owner = $subscription->workspace()->with('owner')->first()?->owner;

        if (! $owner) {
            return;
        }

        try {
            $owner->notify(new SubscriptionUsageNotification($subscription, NotificationTemplateSlug::SUBSCRIPTION_USAGE_LIMIT, $key, $threshold, $used, $limit));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
