<?php

namespace App\Modules\PlansSubscriptions\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Http\Requests\Admin\CancelSubscriptionRequest;
use App\Modules\PlansSubscriptions\Http\Requests\Admin\ChangeSubscriptionPlanRequest;
use App\Modules\PlansSubscriptions\Http\Requests\Admin\ExtendSubscriptionRequest;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use App\Modules\PlansSubscriptions\Services\SubscriptionManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SubscriptionController extends Controller implements HasMiddleware
{
    public function __construct(protected SubscriptionManager $manager) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:subscriptions.view', only: ['index', 'show']),
            new Middleware('permission:subscriptions.manage', except: ['index', 'show']),
        ];
    }

    public function index(Request $request): View
    {
        $subscriptions = Subscription::query()
            ->with(['plan', 'pendingPlan', 'workspace.owner'])
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $request->input('status') === 'expiring'
                    ? $query->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
                        ->whereBetween('renews_at', [now(), now()->addDays(7)])
                    : $query->where('status', $request->input('status'));
            })
            ->when($request->filled('plan_id'), fn (Builder $query) => $query->where('plan_id', $request->integer('plan_id')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search')->trim()->toString().'%';
                $query->whereHas('workspace', fn (Builder $workspace) => $workspace->where('name', 'like', $term)
                    ->orWhereHas('owner', fn (Builder $owner) => $owner->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('plans-subscriptions::admin.subscriptions', [
            'subscriptions' => $subscriptions,
            'statusOptions' => collect(SubscriptionStatus::cases())
                ->mapWithKeys(fn (SubscriptionStatus $status): array => [$status->value => __(str($status->value)->headline()->toString())])
                ->put('expiring', __('Expiring in 7 days'))
                ->all(),
            'planOptions' => Plan::query()->orderBy('sort_order')->pluck('name', 'id')->all(),
        ]);
    }

    public function show(Subscription $subscription, PlanLimitService $limits, SubscriptionAccessService $access): View
    {
        $subscription->load(['plan', 'pendingPlan', 'workspace.owner', 'events.fromPlan', 'events.toPlan', 'events.payment', 'events.actor']);

        return view('plans-subscriptions::admin.subscription-show', [
            'subscription' => $subscription,
            'isUsable' => $access->subscriptionIsUsable($subscription),
            'usageRows' => $limits->usageSnapshot($subscription),
            'plans' => Plan::query()->orderBy('sort_order')->get(),
            'payments' => Payment::query()
                ->where('metadata->workspace_id', $subscription->workspace_id)
                ->whereNotNull('metadata->plan_id')
                ->where('gateway', '!=', 'checkout')
                ->latest()
                ->limit(25)
                ->get(),
        ]);
    }

    public function changePlan(ChangeSubscriptionPlanRequest $request, Subscription $subscription): RedirectResponse
    {
        $plan = Plan::query()->findOrFail($request->integer('plan_id'));
        $subscription->loadMissing('workspace');

        $this->manager->assignByAdmin($subscription->workspace, $plan, $request->user('admin'), $request->boolean('restart_period'), $request->input('note'));

        return back()->with('success', __('The workspace is now on the :plan plan.', ['plan' => $plan->name]));
    }

    public function extend(ExtendSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $subscription = $this->manager->extend($subscription, $request->integer('days'), $request->user('admin'), $request->input('note'));

        return back()->with('success', __('Subscription extended until :date.', ['date' => $subscription->renews_at?->format('M d, Y')]));
    }

    public function cancel(CancelSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $this->manager->cancel($subscription, $request->boolean('at_period_end'), $request->user('admin'));

        return back()->with('success', __('Subscription cancelled.'));
    }

    public function resume(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->manager->resume($subscription, $request->user('admin'));

        return back()->with('success', __('Subscription resumed.'));
    }

    public function resetUsage(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->manager->resetUsage($subscription, $request->user('admin'));

        return back()->with('success', __('Usage counters were reset.'));
    }
}
