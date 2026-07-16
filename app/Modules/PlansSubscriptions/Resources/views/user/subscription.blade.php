@php
    $planName = $currentPlan?->name ?? __('Free');
    $intervalLabel = match ($currentPlan?->interval) {
        'year' => __('year'),
        'lifetime' => __('lifetime'),
        default => __('month'),
    };
    $renewalText = $subscription?->renews_at
        ? __('Renews :date', ['date' => $subscription->renews_at->format('M d, Y')])
        : __('No renewal date set');
    $price = currency_format($currentPlan?->price ?? 0);
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

                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-neutral-100 pt-5">
                    <button type="button" class="btn-sm btn-primary" data-modal-open="changePlan">
                        {{ __('Change plan') }}
                    </button>
                    @if($subscription?->ends_at)
                        <span class="badge badge-warning">
                            {{ __('Ends :date', ['date' => $subscription->ends_at->format('M d, Y')]) }}
                        </span>
                    @endif
                </div>
            </section>

            <section class="app-card p-5 sm:p-6">
                <div class="f-between">
                    <h3 class="heading-4">{{ __('Usage this period') }}</h3>
                    <span class="badge badge-soft">
                        {{ $subscription?->renews_at ? __('Resets :date', ['date' => $subscription->renews_at->format('M d')]) : __('No reset date') }}
                    </span>
                </div>

                <div class="mt-5 space-y-5">
                    @forelse($usageRows as $row)
                        <div>
                            <div class="f-between gap-3 text-sm">
                                <span class="font-semibold text-title">{{ $row['label'] }}</span>
                                <span class="font-semibold text-neutral-500">
                                    {{ number_format($row['used']) }} / {{ number_format($row['maximum']) }}
                                </span>
                            </div>
                            <div class="funnel-track mt-2">
                                <span
                                    class="funnel-bar {{ $row['tone'] === 'warning' ? 'bg-warning' : 'bg-primary' }}"
                                    style="width: {{ $row['percent'] }}%"
                                ></span>
                            </div>
                            @if($row['percent'] >= 90)
                                <p class="form-hint mt-1.5 text-warning">
                                    {{ __('You are close to the :limit limit. Upgrade to avoid interruptions.', ['limit' => $row['label']]) }}
                                </p>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-neutral-200 bg-section p-5 text-center">
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
                        <li class="rounded-lg border border-dashed border-neutral-200 bg-section p-4 text-sm text-neutral-500 sm:col-span-2">
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
                    <div class="list-table" style="--list-cols: minmax(8rem, 1.2fr) minmax(8rem, 1fr) minmax(6rem, .8fr) minmax(6rem, .8fr) 3rem;">
                        <div class="list-table__head">
                            <span>{{ __('Date') }}</span>
                            <span>{{ __('Description') }}</span>
                            <span>{{ __('Amount') }}</span>
                            <span>{{ __('Status') }}</span>
                            <span class="text-right">{{ __('Receipt') }}</span>
                        </div>

                        @forelse($billingRows as $invoice)
                            <div class="list-table__row">
                                <span class="text-xs">{{ $invoice['date'] }}</span>
                                <span class="truncate text-xs">{{ $invoice['description'] }}</span>
                                <span class="text-xs font-semibold text-title">{{ $invoice['amount'] }}</span>
                                <span><span class="badge badge-success">{{ $invoice['status'] }}</span></span>
                                <span class="flex justify-end">
                                    @if($invoice['receipt_url'])
                                        <a href="{{ $invoice['receipt_url'] }}" class="row-action" aria-label="{{ __('Download receipt') }}" target="_blank" rel="noopener">
                                            <i class="ph ph-download-simple text-base"></i>
                                        </a>
                                    @else
                                        <span class="text-xs text-neutral-400">{{ __('—') }}</span>
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
                            <div class="flex items-center gap-3 rounded-lg border border-neutral-200 p-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
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
                    <div class="mt-4 rounded-lg border border-dashed border-neutral-200 bg-section p-4">
                        <p class="text-sm font-semibold text-title">{{ __('No active payment gateways') }}</p>
                        <p class="m-text mt-1">{{ __('Enable a payment gateway from the admin panel before users can pay online.') }}</p>
                    </div>
                @endif
            </div>

            <div class="app-card p-5">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Saved payment method') }}</p>
                @if($paymentMethod)
                    <div class="mt-3 flex items-center gap-3 rounded-lg border border-neutral-200 p-3">
                        <span class="grid h-9 w-12 shrink-0 place-items-center rounded-lg bg-section text-primary">
                            <i class="ph ph-credit-card text-lg"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-title">{{ $paymentMethod['brand'] }} {{ $paymentMethod['last4'] }}</p>
                            <p class="text-xs text-neutral-400">{{ __('Expires :date', ['date' => $paymentMethod['expires']]) }}</p>
                        </div>
                    </div>
                @else
                    <div class="mt-3 rounded-lg border border-dashed border-neutral-200 bg-section p-4">
                        <p class="text-sm font-semibold text-title">{{ __('No payment method on file') }}</p>
                        <p class="m-text mt-1">{{ __('Add a payment provider integration to collect and display card details.') }}</p>
                    </div>
                @endif
            </div>

            <div class="app-card p-5">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-primary/10 text-primary">
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
                        @forelse($plans as $plan)
                            @php
                                $isCurrent = $currentPlan?->id === $plan->id;
                                $planInterval = match ($plan->interval) {
                                    'year' => __('year'),
                                    'lifetime' => __('lifetime'),
                                    default => __('month'),
                                };
                                $numericLimits = collect($plan->limits ?? [])
                                    ->filter(fn ($value): bool => is_numeric($value))
                                    ->take(2);
                            @endphp
                            <label class="plan-pick cursor-pointer">
                                <input type="radio" name="plan_id" value="{{ $plan->id }}" class="sr-only" @checked($isCurrent) />
                                <span class="flex items-center gap-2">
                                    <span class="font-title text-base font-bold text-title">{{ $plan->name }}</span>
                                    @if($isCurrent)
                                        <span class="badge badge-soft">{{ __('Current') }}</span>
                                    @endif
                                </span>
                                <span class="mt-1 block font-title text-2xl font-extrabold text-title">
                                    {{ currency_format($plan->price) }}
                                    <span class="text-sm font-semibold text-neutral-400">
                                        {{ $plan->interval === 'lifetime' ? __('once') : __('/:interval', ['interval' => $planInterval]) }}
                                    </span>
                                </span>
                                <span class="mt-2 block space-y-1 text-xs text-neutral-400">
                                    @forelse($numericLimits as $key => $value)
                                        <span class="block">
                                            {{ __(str($key)->replace('_', ' ')->title()->toString()) }}: {{ number_format((int) $value) }}
                                        </span>
                                    @empty
                                        <span class="block">{{ __('No limits configured') }}</span>
                                    @endforelse
                                </span>
                            </label>
                        @empty
                            <div class="rounded-lg border border-dashed border-neutral-200 bg-section p-6 text-center sm:col-span-2 lg:col-span-3">
                                <p class="font-semibold text-title">{{ __('No active plans found') }}</p>
                                <p class="m-text mt-1">{{ __('Create active plans in the admin panel to show comparison options here.') }}</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-5 flex flex-wrap justify-end gap-3 border-t border-neutral-100 pt-5">
                        <button type="button" class="btn btn-outline" data-modal-close>{{ __('Close') }}</button>
                        @if($plans->isNotEmpty())
                            <button type="submit" class="btn btn-primary">{{ __('Proceed to checkout') }}</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endpush
</x-layouts.user>
