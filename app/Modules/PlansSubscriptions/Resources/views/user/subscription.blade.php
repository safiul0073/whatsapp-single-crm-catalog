@php
    $planName = $currentPlan?->name ?? __('Free');
    $intervalLabel = match ($currentPlan?->interval) {
        'year' => __('year'),
        'lifetime' => __('lifetime'),
        default => __('month'),
    };
    $renewalText = match (true) {
        $currentPlan?->isFree() => __('Free forever'),
        $currentPlan?->isLifetime() => __('Lifetime access'),
        (bool) $subscription?->renews_at && $subscription->cancel_at_period_end => __('Ends :date', ['date' => $subscription->renews_at->format('M d, Y')]),
        (bool) $subscription?->renews_at && $isUsable => __('Paid until :date', ['date' => $subscription->renews_at->format('M d, Y')]),
        (bool) $subscription?->renews_at => __('Expired :date', ['date' => $subscription->renews_at->format('M d, Y')]),
        default => __('No renewal date set'),
    };
    $price = currency_display($currentPlan?->price ?? 0, $currentPlan?->currency);
@endphp

<x-layouts.user :title="__('Subscription')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="heading-2">{{ __('Subscription') }}</h2>
            <p class="m-text mt-1">{{ __('Your plan, usage, and billing history.') }}</p>
        </div>
        <button type="button" class="btn-sm btn-outline" data-modal-open="changePlan">
            <i class="ph ph-stack text-base"></i>
            {{ __('Compare plans') }}
        </button>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <section class="app-card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-title text-xl font-bold text-title">{{ $planName }}</h3>
                            <span class="badge {{ $statusMeta['badge'] }}">{{ $statusMeta['label'] }}</span>
                        </div>
                        <p class="m-text mt-1">
                            @if($subscription)
                                {{ __('Billed :interval · :renewal', ['interval' => $intervalLabel, 'renewal' => $renewalText]) }}
                            @else
                                {{ __('Choose a plan to unlock higher limits and premium features.') }}
                            @endif
                        </p>
                    </div>
                    <div class="text-left sm:text-right">
                        <p class="font-title text-3xl font-extrabold text-title">
                            {{ $price }}
                            <span class="text-base font-semibold text-neutral-400">
                                {{ $currentPlan?->interval === 'lifetime' ? __('once') : __('/:interval', ['interval' => $intervalLabel]) }}
                            </span>
                        </p>
                        <p class="text-xs text-neutral-400">
                            {{ $currentPlan ? __('Current plan price') : __('No plan selected') }}
                        </p>
                    </div>
                </div>

                @if($subscription && ! $isUsable)
                    <div class="mt-5 rounded-md border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">
                        {{ __('Your workspace is read-only. Renew or choose a plan to start sending again.') }}
                    </div>
                @elseif($subscription?->pendingPlan)
                    <div class="mt-5 rounded-md border border-warning/20 bg-warning/10 px-4 py-3 text-sm font-medium text-title">
                        {{ __('You will move to the :plan plan on :date.', ['plan' => $subscription->pendingPlan->name, 'date' => $subscription->renews_at?->format('M d, Y')]) }}
                    </div>
                @elseif($subscription?->cancel_at_period_end)
                    <div class="mt-5 rounded-md border border-warning/20 bg-warning/10 px-4 py-3 text-sm font-medium text-title">
                        {{ __('Your plan will not renew. Access continues until :date.', ['date' => $subscription->renews_at?->format('M d, Y')]) }}
                    </div>
                @endif

                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-neutral-100 pt-5">
                    @if($canRenew && ! $subscription->cancel_at_period_end)
                        <form method="POST" action="{{ route('user.subscription.checkout.initiate') }}">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $subscription->plan_id }}">
                            <button type="submit" class="btn-sm btn-primary">
                                <i class="ph ph-arrows-clockwise text-base"></i>
                                {{ __('Renew now') }}
                            </button>
                        </form>
                    @endif
                    <button type="button" class="btn-sm {{ $canRenew ? 'btn-outline' : 'btn-primary' }}" data-modal-open="changePlan">
                        {{ __('Change plan') }}
                    </button>
                    @if($subscription && ($subscription->cancel_at_period_end || $subscription->pending_plan_id))
                        <form method="POST" action="{{ route('user.subscription.resume') }}">
                            @csrf
                            <button type="submit" class="btn-sm btn-outline">{{ __('Keep current plan') }}</button>
                        </form>
                    @elseif($isUsable && $subscription->renews_at && ! $currentPlan?->isFree())
                        <form method="POST" action="{{ route('user.subscription.cancel') }}">
                            @csrf
                            <button type="submit" class="btn-sm btn-outline text-error">{{ __('Cancel at period end') }}</button>
                        </form>
                    @endif
                </div>
            </section>

            <section class="app-card p-5 sm:p-6">
                <div class="f-between">
                    <h3 class="heading-4">{{ __('Usage this period') }}</h3>
                    <span class="badge badge-soft">
                        {{ $usageResetsAt ? __('Monthly quotas reset :date', ['date' => $usageResetsAt->format('M d')]) : __('No reset date') }}
                    </span>
                </div>

                <div class="mt-5 space-y-5">
                    @forelse($usageRows as $row)
                        <div>
                            <div class="f-between gap-3 text-sm">
                                <span class="font-semibold text-title">{{ $row['label'] }}</span>
                                <span class="font-semibold text-neutral-500">
                                    @if($row['maximum'] === null)
                                        {{ number_format($row['used']) }} · {{ __('Unlimited') }}
                                    @else
                                        {{ number_format($row['used']) }} / {{ number_format($row['maximum']) }}
                                    @endif
                                </span>
                            </div>
                            <div class="funnel-track mt-2">
                                <span
                                    class="funnel-bar {{ match ($row['tone']) { 'error' => 'bg-error', 'warning' => 'bg-warning', default => 'bg-primary' } }}"
                                    style="width: {{ $row['percent'] }}%"
                                ></span>
                            </div>
                            @if($row['maximum'] !== null && $row['percent'] >= 100)
                                <p class="form-hint mt-1.5 text-error">
                                    {{ __('You have reached the :limit limit. Upgrade to continue.', ['limit' => $row['label']]) }}
                                </p>
                            @elseif($row['maximum'] !== null && $row['percent'] >= 80)
                                <p class="form-hint mt-1.5 text-warning">
                                    {{ __('You are close to the :limit limit. Upgrade to avoid interruptions.', ['limit' => $row['label']]) }}
                                </p>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-md border border-dashed border-neutral-200 bg-section p-5 text-center">
                            <p class="font-semibold text-title">{{ __('No usage limits configured') }}</p>
                            <p class="m-text mt-1">{{ __('Add limits to this plan from the admin panel to show usage here.') }}</p>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="app-card p-5 sm:p-6">
                <h3 class="heading-4">{{ __('What is included') }}</h3>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse($featureRows as $feature)
                        <li class="flex items-start gap-2.5 text-sm text-body">
                            <i class="ph ph-check-circle mt-0.5 text-base text-success"></i>
                            <span>{{ $feature }}</span>
                        </li>
                    @empty
                        <li class="rounded-md border border-dashed border-neutral-200 bg-section p-4 text-sm text-neutral-500 sm:col-span-2">
                            {{ __('No feature list has been added to this plan yet.') }}
                        </li>
                    @endforelse
                </ul>
            </section>

            <section class="app-card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-100 p-5">
                    <h3 class="heading-4">{{ __('Billing history') }}</h3>
                    <span class="badge badge-soft">{{ trans_choice(':count invoice|:count invoices', $billingRows->count()) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <div class="list-table" style="--list-cols: minmax(8rem, 1.2fr) minmax(8rem, 1fr) minmax(6rem, .8fr) minmax(6rem, .8fr) 5rem;">
                        <div class="list-table__head">
                            <span>{{ __('Date') }}</span>
                            <span>{{ __('Description') }}</span>
                            <span>{{ __('Amount') }}</span>
                            <span>{{ __('Status') }}</span>
                            <span class="text-right">{{ __('Receipt') }}</span>
                        </div>

                        @forelse($billingRows as $invoice)
                            <div class="list-table__row">
                                <span class="text-xs">{{ $invoice['date'] }}@if($invoice['number'])<span class="block text-neutral-400">{{ $invoice['number'] }}</span>@endif</span>
                                <span class="truncate text-xs">{{ $invoice['description'] }}</span>
                                <span class="text-xs font-semibold text-title">{{ $invoice['amount'] }}</span>
                                <span><span class="badge badge-success">{{ $invoice['status'] }}</span></span>
                                <span class="flex justify-end gap-1">
                                    <a href="{{ $invoice['invoice_url'] }}" class="row-action" aria-label="{{ __('View invoice') }}" target="_blank" rel="noopener">
                                        <i class="ph ph-file-text text-base"></i>
                                    </a>
                                    @if($invoice['receipt_url'])
                                        <a href="{{ $invoice['receipt_url'] }}" class="row-action" aria-label="{{ __('Gateway receipt') }}" target="_blank" rel="noopener">
                                            <i class="ph ph-download-simple text-base"></i>
                                        </a>
                                    @endif
                                </span>
                            </div>
                        @empty
                            <div class="p-6 text-center">
                                <p class="font-semibold text-title">{{ __('No billing history yet') }}</p>
                                <p class="m-text mt-1">{{ __('Invoices and receipts will appear here after successful payments.') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <div class="app-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Payment gateways') }}</p>
                        <p class="m-text mt-1">{{ __('Available methods for subscription payments.') }}</p>
                    </div>
                    <span class="badge badge-soft">{{ $enabledPaymentGateways->count() }}</span>
                </div>

                @if($enabledPaymentGateways->isNotEmpty())
                    <div class="mt-4 space-y-2.5">
                        @foreach($enabledPaymentGateways as $gateway)
                            <div class="flex items-center gap-3 rounded-md border border-neutral-200 p-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-primary/10 text-primary">
                                    <i class="ph {{ $gateway['icon'] }} text-lg"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-title">{{ $gateway['label'] }}</p>
                                    <p class="truncate text-xs text-neutral-400">{{ $gateway['description'] }}</p>
                                </div>
                                <span class="badge badge-success">{{ __('Active') }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-4 rounded-md border border-dashed border-neutral-200 bg-section p-4">
                        <p class="text-sm font-semibold text-title">{{ __('No active payment gateways') }}</p>
                        <p class="m-text mt-1">{{ __('Enable a payment gateway from the admin panel before users can pay online.') }}</p>
                    </div>
                @endif
            </div>

            <div class="app-card p-5">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Saved payment method') }}</p>
                @if($paymentMethod)
                    <div class="mt-3 flex items-center gap-3 rounded-md border border-neutral-200 p-3">
                        <span class="grid h-9 w-12 shrink-0 place-items-center rounded-md bg-section text-primary">
                            <i class="ph ph-credit-card text-lg"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-title">{{ $paymentMethod['brand'] }} {{ $paymentMethod['last4'] }}</p>
                            <p class="text-xs text-neutral-400">{{ __('Expires :date', ['date' => $paymentMethod['expires']]) }}</p>
                        </div>
                    </div>
                @else
                    <div class="mt-3 rounded-md border border-dashed border-neutral-200 bg-section p-4">
                        <p class="text-sm font-semibold text-title">{{ __('No payment method on file') }}</p>
                        <p class="m-text mt-1">{{ __('Add a payment provider integration to collect and display card details.') }}</p>
                    </div>
                @endif
            </div>

            <div class="app-card p-5">
                <span class="grid h-10 w-10 place-items-center rounded-md bg-primary/10 text-primary">
                    <i class="ph ph-rocket-launch text-xl"></i>
                </span>
                <p class="mt-3 font-title text-base font-bold text-title">{{ __('Need more headroom?') }}</p>
                <p class="m-text mt-1">{{ __('Compare active plans and choose the limits that fit your workspace.') }}</p>
                <button type="button" class="btn-sm btn-primary mt-4 w-full" data-modal-open="changePlan">
                    {{ __('Compare plans') }}
                </button>
            </div>
        </aside>
    </div>

    @push('modals')
        <div class="modal" id="changePlan" data-modal>
            <div class="modal__backdrop" data-modal-close></div>
            <div class="modal__panel modal__panel--lg" role="dialog" aria-modal="true" aria-labelledby="changePlanTitle">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="changePlanTitle" class="heading-4">{{ __('Compare plans') }}</h3>
                    <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                        <i class="ph ph-x text-base"></i>
                    </button>
                </div>

                <form action="{{ route('user.subscription.checkout.initiate') }}" method="POST">
                    @csrf
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse($planOptions as $option)
                            @php
                                $plan = $option['plan'];
                                $isCurrent = $isUsable && $currentPlan?->id === $plan->id;
                                $planInterval = match ($plan->interval) {
                                    'year' => __('year'),
                                    'lifetime' => __('lifetime'),
                                    default => __('month'),
                                };
                                $numericLimits = collect($plan->limits ?? [])
                                    ->filter(fn ($value): bool => is_numeric($value) && ! is_bool($value))
                                    ->take(3);
                            @endphp
                            <label class="plan-pick {{ $option['disabled'] ? 'cursor-not-allowed opacity-60' : 'cursor-pointer' }}">
                                <input type="radio" name="plan_id" value="{{ $plan->id }}" class="sr-only" @checked($isCurrent) @disabled($option['disabled']) />
                                <span class="flex items-center gap-2">
                                    <span class="font-title text-base font-bold text-title">{{ $plan->name }}</span>
                                    @if($isCurrent)
                                        <span class="badge badge-soft">{{ __('Current') }}</span>
                                    @endif
                                </span>
                                <span class="mt-1 block font-title text-2xl font-extrabold text-title">
                                    {{ currency_display($plan->price, $plan->currency) }}
                                    <span class="text-sm font-semibold text-neutral-400">
                                        {{ $plan->interval === 'lifetime' ? __('once') : __('/:interval', ['interval' => $planInterval]) }}
                                    </span>
                                </span>
                                <span class="mt-2 block space-y-1 text-xs text-neutral-400">
                                    @forelse($numericLimits as $key => $value)
                                        <span class="block">
                                            {{ \App\Modules\PlansSubscriptions\Services\PlanLimitService::label($key) }}: {{ number_format((int) $value) }}
                                        </span>
                                    @empty
                                        <span class="block">{{ __('No limits configured') }}</span>
                                    @endforelse
                                </span>
                                @php
                                    $includedModules = collect($planModules)->filter(fn (string $label, string $module): bool => $plan->includesFeature($module));
                                @endphp
                                <span class="mt-2 block text-xs text-neutral-500">
                                    @if($includedModules->count() === count($planModules))
                                        {{ __('All modules included') }}
                                    @else
                                        {{ __('Includes: :modules', ['modules' => $includedModules->implode(', ') ?: __('Dashboard only')]) }}
                                    @endif
                                </span>
                                <span class="mt-3 block text-xs font-semibold text-primary">{{ $option['label'] }}</span>
                                @if($option['intent'] === 'upgrade' && (float) $option['credit'] > 0)
                                    <span class="mt-1 block text-xs text-neutral-500">
                                        {{ __('Credit :credit · Pay :due today', ['credit' => currency_display($option['credit']), 'due' => currency_display($option['amount_due'])]) }}
                                    </span>
                                @elseif($option['intent'] === 'downgrade' && $subscription?->renews_at)
                                    <span class="mt-1 block text-xs text-neutral-500">
                                        {{ __('Starts :date', ['date' => $subscription->renews_at->format('M d, Y')]) }}
                                    </span>
                                @endif
                            </label>
                        @empty
                            <div class="rounded-md border border-dashed border-neutral-200 bg-section p-6 text-center sm:col-span-2 lg:col-span-3">
                                <p class="font-semibold text-title">{{ __('No active plans found') }}</p>
                                <p class="m-text mt-1">{{ __('Create active plans in the admin panel to show comparison options here.') }}</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-5 flex flex-wrap justify-end gap-3 border-t border-neutral-100 pt-5">
                        <button type="button" class="btn btn-outline" data-modal-close>{{ __('Close') }}</button>
                        @if($plans->isNotEmpty())
                            <button type="submit" class="btn btn-primary">{{ __('Continue') }}</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endpush
</x-layouts.user>
