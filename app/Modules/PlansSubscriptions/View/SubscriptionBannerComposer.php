<?php

namespace App\Modules\PlansSubscriptions\View;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Jobs\SendSubscriptionExpiryReminderJob;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Supplies the user-panel banner shown when the workspace plan is expiring or no longer usable.
 */
class SubscriptionBannerComposer
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected SubscriptionAccessService $access,
    ) {}

    public function compose(View $view): void
    {
        $view->with('subscriptionBanner', $this->banner());
    }

    /**
     * @return array{tone: string, message: string, can_manage: bool}|null
     */
    protected function banner(): ?array
    {
        $user = auth()->user();

        if (! $user || request()->routeIs('user.subscription.*')) {
            return null;
        }

        $workspace = $this->workspaces->current($user);
        $subscription = $workspace ? $this->access->currentForWorkspace($workspace->id) : null;

        if (! $subscription) {
            return null;
        }

        $canManage = Gate::allows('subscription.manage');

        if (! $this->access->subscriptionIsUsable($subscription)) {
            return [
                'tone' => 'error',
                'message' => __('Your :plan plan has expired. The workspace is read-only until it is renewed.', ['plan' => $subscription->plan?->name ?? __('current')]),
                'can_manage' => $canManage,
            ];
        }

        if (! $subscription->cancel_at_period_end && $this->access->expiresSoon($subscription, SendSubscriptionExpiryReminderJob::REMIND_DAYS_BEFORE)) {
            return [
                'tone' => 'warning',
                'message' => __('Your :plan plan expires on :date. Renew to avoid interruptions.', [
                    'plan' => $subscription->plan?->name,
                    'date' => $subscription->renews_at->format('M d, Y'),
                ]),
                'can_manage' => $canManage,
            ];
        }

        return null;
    }
}
