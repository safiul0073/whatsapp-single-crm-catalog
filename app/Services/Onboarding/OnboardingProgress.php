<?php

namespace App\Services\Onboarding;

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class OnboardingProgress
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected SubscriptionAccessService $subscriptions,
    ) {}

    public function workspaceFor(User $user): Workspace
    {
        return $this->workspaces->current($user);
    }

    public function workspaceIsComplete(?Workspace $workspace): bool
    {
        return filled(data_get($workspace?->settings, 'onboarding_completed_at'));
    }

    public function subscriptionIsComplete(?Workspace $workspace): bool
    {
        if (! $workspace) {
            return false;
        }

        return $this->subscriptions->isActiveForUse($workspace->id);
    }

    public function hasSubscription(?Workspace $workspace): bool
    {
        if (! $workspace) {
            return false;
        }

        return Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->exists();
    }

    public function pendingPayment(?Workspace $workspace): ?Payment
    {
        if (! $workspace) {
            return null;
        }

        return Payment::query()
            ->where('status', 'pending')
            ->where('metadata->workspace_id', $workspace->id)
            ->whereNotNull('metadata->plan_id')
            ->latest()
            ->first();
    }

    public function nextRoute(User $user): string
    {
        $workspace = $this->workspaceFor($user);

        if (! $this->workspaceIsComplete($workspace)) {
            return route('onboarding.workspace');
        }

        if (! $this->subscriptionIsComplete($workspace)) {
            if ($payment = $this->pendingPayment($workspace)) {
                return route('onboarding.checkout', $payment);
            }

            return route('onboarding.plan');
        }

        return route('user.dashboard');
    }

    public function redirect(User $user): RedirectResponse
    {
        return redirect()->to($this->nextRoute($user));
    }

    public function completeWorkspace(Workspace $workspace, array $data): Workspace
    {
        $settings = $workspace->settings ?? [];
        $settings['category'] = $data['category'];
        $settings['team_size'] = $data['team_size'];
        $settings['onboarding_completed_at'] = Carbon::now()->toIso8601String();

        $workspace->update([
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'settings' => $settings,
        ]);

        return $workspace->fresh();
    }

    public function activatePlan(Workspace $workspace, Plan $plan, string $status = SubscriptionStatus::Active->value): Subscription
    {
        return Subscription::query()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'plan_id' => $plan->id,
                'status' => $status,
                'starts_at' => now(),
                'renews_at' => $plan->interval === 'year'
                    ? now()->addYear()
                    : ($plan->interval === 'month' ? now()->addMonth() : null),
                'ends_at' => null,
                'usage' => [],
            ]
        );
    }
}
