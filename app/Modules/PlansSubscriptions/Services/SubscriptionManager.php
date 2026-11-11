<?php

namespace App\Modules\PlansSubscriptions\Services;

use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Enums\CheckoutIntent;
use App\Modules\PlansSubscriptions\Enums\SubscriptionEventType;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Models\SubscriptionEvent;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single writer for subscription rows. Every change is logged as a SubscriptionEvent.
 */
class SubscriptionManager
{
    public function __construct(
        protected SubscriptionAccessService $access,
        protected PlanLimitService $limits,
    ) {}

    /**
     * Start (or restart) a plan for a workspace right now with a fresh period and usage.
     */
    public function activate(Workspace $workspace, Plan $plan, ?Payment $payment = null, ?Model $actor = null, SubscriptionStatus $status = SubscriptionStatus::Active): Subscription
    {
        return DB::transaction(function () use ($workspace, $plan, $payment, $actor, $status): Subscription {
            $subscription = $this->lockedFor($workspace->id);
            $fromPlanId = $subscription?->plan_id;
            $subscription ??= new Subscription(['workspace_id' => $workspace->id]);

            $subscription->forceFill([
                'plan_id' => $plan->id,
                'status' => $status,
                'starts_at' => now(),
                'renews_at' => $this->periodEnd(now(), $plan),
                'ends_at' => null,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'pending_plan_id' => null,
                'last_payment_id' => $payment?->id ?? $subscription->last_payment_id,
                'usage' => [],
                'usage_period_start' => now(),
            ])->save();

            $this->record($subscription, SubscriptionEventType::Activated, $fromPlanId, $plan->id, $payment, $actor);

            return $subscription->fresh('plan');
        });
    }

    /**
     * Apply a completed payment to its workspace subscription. Safe to call more than once per payment.
     */
    public function applyPayment(Payment $payment): ?Subscription
    {
        $planId = (int) data_get($payment->metadata, 'plan_id');
        $workspaceId = (int) data_get($payment->metadata, 'workspace_id');

        if (! $planId || ! $workspaceId || $payment->status !== 'completed') {
            return null;
        }

        $plan = Plan::query()->find($planId);
        $workspace = Workspace::query()->find($workspaceId);

        if (! $plan || ! $workspace) {
            return null;
        }

        return DB::transaction(function () use ($payment, $plan, $workspace): Subscription {
            $subscription = $this->lockedFor($workspace->id);

            if ($subscription && $this->paymentAlreadyApplied($payment)) {
                return $subscription->fresh('plan');
            }

            $intent = CheckoutIntent::tryFrom((string) data_get($payment->metadata, 'intent')) ?? CheckoutIntent::New;
            $usable = $subscription && $this->access->subscriptionIsUsable($subscription);

            if ($usable && $subscription->plan_id === $plan->id && $intent !== CheckoutIntent::Upgrade) {
                return $this->renewLocked($subscription, $plan, $payment);
            }

            if ($usable && $intent === CheckoutIntent::Upgrade) {
                return $this->upgradeLocked($subscription, $plan, $payment);
            }

            if ($usable && $subscription->renews_at && $subscription->plan_id !== $plan->id) {
                return $this->scheduleDowngradeLocked($subscription, $plan, $payment, $payment->user);
            }

            return $this->activate($workspace, $plan, $payment, $payment->user);
        });
    }

    /**
     * Extend the same plan by one period, starting from the later of now and the current renewal date.
     */
    protected function renewLocked(Subscription $subscription, Plan $plan, ?Payment $payment, ?Model $actor = null): Subscription
    {
        $from = $subscription->renews_at && $subscription->renews_at->isFuture() ? $subscription->renews_at : now();

        $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'renews_at' => $this->periodEnd($from, $plan),
            'ends_at' => null,
            'cancelled_at' => null,
            'cancel_at_period_end' => false,
            'last_payment_id' => $payment?->id ?? $subscription->last_payment_id,
        ])->save();

        $this->record($subscription, SubscriptionEventType::Renewed, $plan->id, $plan->id, $payment, $actor ?? $payment?->user, [
            'renews_at' => $subscription->renews_at?->toIso8601String(),
        ]);

        return $subscription->fresh('plan');
    }

    /**
     * Switch to a higher plan immediately. A new period starts now; usage carries over.
     */
    protected function upgradeLocked(Subscription $subscription, Plan $plan, ?Payment $payment, ?Model $actor = null): Subscription
    {
        $fromPlanId = $subscription->plan_id;

        $subscription->forceFill([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
            'renews_at' => $this->periodEnd(now(), $plan),
            'ends_at' => null,
            'cancelled_at' => null,
            'cancel_at_period_end' => false,
            'pending_plan_id' => null,
            'last_payment_id' => $payment?->id ?? $subscription->last_payment_id,
            'usage_period_start' => $subscription->usage_period_start ?? now(),
        ])->save();

        $this->record($subscription, SubscriptionEventType::Upgraded, $fromPlanId, $plan->id, $payment, $actor ?? $payment?->user, [
            'credit' => data_get($payment?->metadata, 'proration_credit'),
        ]);

        return $subscription->fresh('plan');
    }

    /**
     * Move to a cheaper (or free) plan when the current paid period ends.
     */
    public function scheduleDowngrade(Subscription $subscription, Plan $plan, ?Model $actor = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $plan, $actor): Subscription {
            $subscription = $this->lockedFor($subscription->workspace_id);

            if (! $subscription->renews_at || ! $this->access->subscriptionIsUsable($subscription)) {
                $workspace = Workspace::query()->findOrFail($subscription->workspace_id);

                return $this->activate($workspace, $plan, null, $actor);
            }

            return $this->scheduleDowngradeLocked($subscription, $plan, null, $actor);
        });
    }

    /**
     * A paid downgrade ($payment set) is prepaid and starts when the current period ends.
     */
    protected function scheduleDowngradeLocked(Subscription $subscription, Plan $plan, ?Payment $payment, ?Model $actor = null): Subscription
    {
        $subscription->forceFill([
            'pending_plan_id' => $plan->id,
            'cancel_at_period_end' => false,
            'last_payment_id' => $payment?->id ?? $subscription->last_payment_id,
        ])->save();

        $this->record($subscription, SubscriptionEventType::DowngradeScheduled, $subscription->plan_id, $plan->id, $payment, $actor, [
            'effective_at' => $subscription->renews_at?->toIso8601String(),
            'prepaid' => $payment !== null,
        ]);

        return $subscription->fresh('plan');
    }

    public function cancel(Subscription $subscription, bool $atPeriodEnd = true, ?Model $actor = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $atPeriodEnd, $actor): Subscription {
            $subscription = $this->lockedFor($subscription->workspace_id);

            $attributes = ['cancelled_at' => now(), 'pending_plan_id' => null];

            if ($atPeriodEnd && $subscription->renews_at?->isFuture()) {
                $attributes['cancel_at_period_end'] = true;
            } else {
                $attributes['status'] = SubscriptionStatus::Cancelled;
                $attributes['ends_at'] = now();
                $attributes['cancel_at_period_end'] = false;
            }

            $subscription->forceFill($attributes)->save();

            $this->record($subscription, SubscriptionEventType::Cancelled, $subscription->plan_id, null, null, $actor, ['at_period_end' => $atPeriodEnd]);

            return $subscription->fresh('plan');
        });
    }

    /**
     * Undo a scheduled cancellation / downgrade, or reactivate a cancelled plan that still has time left.
     */
    public function resume(Subscription $subscription, ?Model $actor = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $subscription = $this->lockedFor($subscription->workspace_id);

            $attributes = ['cancel_at_period_end' => false, 'cancelled_at' => null, 'pending_plan_id' => null];

            if ($subscription->status === SubscriptionStatus::Cancelled && (! $subscription->renews_at || $subscription->renews_at->isFuture())) {
                $attributes['status'] = SubscriptionStatus::Active;
                $attributes['ends_at'] = null;
            }

            $subscription->forceFill($attributes)->save();

            $this->record($subscription, SubscriptionEventType::Resumed, $subscription->plan_id, $subscription->plan_id, null, $actor);

            return $subscription->fresh('plan');
        });
    }

    /**
     * Give extra days (admin goodwill / comp). Reactivates an expired subscription.
     */
    public function extend(Subscription $subscription, int $days, ?Model $actor = null, ?string $note = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $days, $actor, $note): Subscription {
            $subscription = $this->lockedFor($subscription->workspace_id);
            $from = $subscription->renews_at && $subscription->renews_at->isFuture() ? $subscription->renews_at : now();

            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'renews_at' => $from->copy()->addDays($days),
                'ends_at' => null,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
            ])->save();

            $this->record($subscription, SubscriptionEventType::Extended, $subscription->plan_id, $subscription->plan_id, null, $actor, [
                'days' => $days,
                'note' => $note,
                'renews_at' => $subscription->renews_at->toIso8601String(),
            ]);

            return $subscription->fresh('plan');
        });
    }

    /**
     * Admin assigns a plan without a payment. Keeps the remaining period unless $restartPeriod is set.
     */
    public function assignByAdmin(Workspace $workspace, Plan $plan, Model $actor, bool $restartPeriod = true, ?string $note = null): Subscription
    {
        return DB::transaction(function () use ($workspace, $plan, $actor, $restartPeriod, $note): Subscription {
            $subscription = $this->lockedFor($workspace->id);
            $fromPlanId = $subscription?->plan_id;
            $subscription ??= new Subscription(['workspace_id' => $workspace->id, 'usage' => [], 'usage_period_start' => now()]);

            $keepPeriod = ! $restartPeriod && $subscription->exists && $subscription->renews_at?->isFuture() && ! $plan->neverExpires();

            $subscription->forceFill([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => $keepPeriod ? $subscription->starts_at : now(),
                'renews_at' => $keepPeriod ? $subscription->renews_at : $this->periodEnd(now(), $plan),
                'ends_at' => null,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'pending_plan_id' => null,
            ])->save();

            $this->record($subscription, SubscriptionEventType::AdminChanged, $fromPlanId, $plan->id, null, $actor, ['note' => $note, 'restart_period' => $restartPeriod]);

            return $subscription->fresh('plan');
        });
    }

    public function resetUsage(Subscription $subscription, ?Model $actor = null): Subscription
    {
        $this->limits->resetUsage($subscription);
        $this->record($subscription, SubscriptionEventType::UsageReset, $subscription->plan_id, $subscription->plan_id, null, $actor);

        return $subscription->fresh('plan');
    }

    /**
     * Called by the scheduler once a period has ended: applies a scheduled downgrade, a scheduled
     * cancellation, or marks the subscription expired. Returns true when the subscription expired.
     */
    public function closePeriod(Subscription $subscription): bool
    {
        return DB::transaction(function () use ($subscription): bool {
            $subscription = $this->lockedFor($subscription->workspace_id);

            if (! $subscription || ! $this->access->shouldExpire($subscription)) {
                return false;
            }

            if ($subscription->pending_plan_id && ! $subscription->cancel_at_period_end) {
                $plan = Plan::query()->find($subscription->pending_plan_id);

                $prepaidPayment = $plan && ! $plan->isFree() ? $this->prepaidDowngradePayment($subscription, $plan) : null;

                if ($plan && ($plan->isFree() || $prepaidPayment)) {
                    $fromPlanId = $subscription->plan_id;
                    $periodStart = $subscription->renews_at ?? now();

                    $subscription->forceFill([
                        'plan_id' => $plan->id,
                        'status' => SubscriptionStatus::Active,
                        'starts_at' => $periodStart,
                        'renews_at' => $this->periodEnd($periodStart, $plan),
                        'ends_at' => null,
                        'pending_plan_id' => null,
                        'usage' => [],
                        'usage_period_start' => now(),
                    ])->save();

                    $this->record($subscription, SubscriptionEventType::Downgraded, $fromPlanId, $plan->id, null, null, [
                        'prepaid_payment_id' => $prepaidPayment?->id,
                    ]);

                    // A prepaid period may itself already be over if the scheduler was down for a long time.
                    return $this->access->shouldExpire($subscription) ? $this->closePeriod($subscription) : false;
                }

                // An unpaid downgrade to a paid plan: the user still has to pay for it; fall through and expire.
            }

            $expiresAt = $this->access->expiryDate($subscription) ?? now();

            $subscription->forceFill([
                'status' => $subscription->cancel_at_period_end ? SubscriptionStatus::Cancelled : SubscriptionStatus::Expired,
                'ends_at' => $subscription->ends_at ?? $expiresAt,
                'cancel_at_period_end' => false,
            ])->save();

            $this->record($subscription, SubscriptionEventType::Expired, $subscription->plan_id, null, null, null, [
                'expired_at' => $expiresAt->toIso8601String(),
            ]);

            return true;
        });
    }

    public function periodEnd(Carbon $from, Plan $plan): ?Carbon
    {
        if ($plan->neverExpires()) {
            return null;
        }

        return $plan->interval === Plan::INTERVAL_YEAR
            ? $from->copy()->addYearNoOverflow()
            : $from->copy()->addMonthNoOverflow();
    }

    protected function lockedFor(int $workspaceId): ?Subscription
    {
        return Subscription::query()->with('plan')->where('workspace_id', $workspaceId)->lockForUpdate()->first();
    }

    protected function prepaidDowngradePayment(Subscription $subscription, Plan $plan): ?Payment
    {
        // Only the most recent schedule counts, and only if it has not been applied yet.
        $event = SubscriptionEvent::query()
            ->with('payment')
            ->where('subscription_id', $subscription->id)
            ->where('type', SubscriptionEventType::DowngradeScheduled->value)
            ->latest('id')
            ->first();

        if (! $event || $event->to_plan_id !== $plan->id || $event->payment?->status !== 'completed') {
            return null;
        }

        $alreadyApplied = SubscriptionEvent::query()
            ->where('subscription_id', $subscription->id)
            ->where('type', SubscriptionEventType::Downgraded->value)
            ->where('id', '>', $event->id)
            ->exists();

        return $alreadyApplied ? null : $event->payment;
    }

    protected function paymentAlreadyApplied(Payment $payment): bool
    {
        return SubscriptionEvent::query()->where('payment_id', $payment->id)->exists();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function record(Subscription $subscription, SubscriptionEventType $type, ?int $fromPlanId = null, ?int $toPlanId = null, ?Payment $payment = null, ?Model $actor = null, array $meta = []): void
    {
        SubscriptionEvent::query()->create([
            'subscription_id' => $subscription->id,
            'type' => $type,
            'from_plan_id' => $fromPlanId,
            'to_plan_id' => $toPlanId,
            'payment_id' => $payment?->id,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'meta' => array_filter($meta, fn (mixed $value): bool => $value !== null) ?: null,
        ]);
    }
}
