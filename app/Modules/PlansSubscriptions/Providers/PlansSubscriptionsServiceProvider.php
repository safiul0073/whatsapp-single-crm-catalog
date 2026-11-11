<?php

namespace App\Modules\PlansSubscriptions\Providers;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\PaymentGateways\Events\PaymentSucceeded;
use App\Modules\PlansSubscriptions\Console\Commands\BackfillFreeSubscriptions;
use App\Modules\PlansSubscriptions\Listeners\ActivateSubscriptionFromPayment;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use App\Modules\PlansSubscriptions\Services\SubscriptionManager;
use App\Modules\PlansSubscriptions\View\SubscriptionBannerComposer;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

class PlansSubscriptionsServiceProvider extends BasePanelModuleProvider
{
    protected function bootModule(array $module): void
    {
        Event::listen(PaymentSucceeded::class, ActivateSubscriptionFromPayment::class);
        View::composer('plans-subscriptions::partials.status-banner', SubscriptionBannerComposer::class);

        Workspace::created(function (Workspace $workspace): void {
            if (! Schema::hasTable('plans')) {
                return;
            }

            $freePlan = Plan::query()->active()->where('is_free', true)->orderBy('sort_order')->first()
                ?? Plan::query()->active()->where('price', 0)->where('interval', '!=', Plan::INTERVAL_LIFETIME)->orderBy('sort_order')->first();

            if ($freePlan) {
                app(SubscriptionManager::class)->activate($workspace, $freePlan, null, $workspace->owner);
            }
        });

        ChannelAccount::creating(function (ChannelAccount $account): void {
            if ($account->provider === 'whatsapp' && app(PlanLimitService::class)->reached((int) $account->workspace_id, 'whatsapp_numbers')) {
                throw ValidationException::withMessages([
                    'channel' => __('Your plan WhatsApp number limit has been reached. Upgrade your plan to connect another number.'),
                ]);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([BackfillFreeSubscriptions::class]);
        }
    }
}
