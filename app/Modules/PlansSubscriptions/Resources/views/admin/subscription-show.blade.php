@php
    $owner = $subscription->workspace?->owner;
    $status = $subscription->status?->value ?? (string) $subscription->status;
    $statusVariant = match (true) {
        ! $isUsable => 'error',
        $subscription->cancel_at_period_end => 'warning',
        default => 'success',
    };
@endphp

<x-layouts.admin :title="__('Subscription')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('admin.subscriptions.index') }}" class="inline-flex items-center gap-1 text-sm text-neutral-600 hover:text-neutral-950">
                    <i class="ph ph-arrow-left"></i> {{ __('Subscriptions') }}
                </a>
                <h1 class="heading-4 mt-1 text-neutral-950">{{ $subscription->workspace?->name ?? __('Deleted workspace') }}</h1>
                <p class="text-sm text-neutral-600">{{ $owner?->name }} · {{ $owner?->email }}</p>
            </div>
            <x-ui.badge :variant="$statusVariant">{{ $isUsable ? ucfirst(str_replace('_', ' ', $status)) : __('Not usable (:status)', ['status' => str_replace('_', ' ', $status)]) }}</x-ui.badge>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="section-card">
                    <h2 class="heading-5 text-neutral-950">{{ __('Current plan') }}</h2>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-neutral-400">{{ __('Plan') }}</dt>
                            <dd class="font-semibold text-neutral-950">{{ $subscription->plan?->name ?? __('No plan') }}</dd>
                            @if ($subscription->plan)
                                <dd class="text-neutral-600">{{ currency_format($subscription->plan->price) }} · {{ ucfirst($subscription->plan->interval) }}</dd>
                            @endif
                        </div>
                        <div>
                            <dt class="text-neutral-400">{{ __('Paid until') }}</dt>
                            <dd class="font-semibold text-neutral-950">{{ $subscription->renews_at?->format('M d, Y H:i') ?? __('Never expires') }}</dd>
                            @if ($subscription->ends_at)
                                <dd class="text-neutral-600">{{ __('Ended') }}: {{ $subscription->ends_at->format('M d, Y') }}</dd>
                            @endif
                        </div>
                        <div>
                            <dt class="text-neutral-400">{{ __('Period started') }}</dt>
                            <dd class="text-neutral-950">{{ $subscription->starts_at?->format('M d, Y') ?? __('Not set') }}</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">{{ __('Scheduled change') }}</dt>
                            <dd class="text-neutral-950">
                                @if ($subscription->pendingPlan)
                                    {{ __('Moves to :plan at period end', ['plan' => $subscription->pendingPlan->name]) }}
                                @elseif ($subscription->cancel_at_period_end)
                                    {{ __('Cancels at period end') }}
                                @else
                                    {{ __('None') }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="section-card">
                    <div class="flex items-center justify-between">
                        <h2 class="heading-5 text-neutral-950">{{ __('Usage') }}</h2>
                        <form method="POST" action="{{ route('admin.subscriptions.reset-usage', $subscription) }}">
                            @csrf
                            <x-ui.button type="submit" variant="outline" size="sm"><i class="ph ph-arrow-counter-clockwise"></i> {{ __('Reset monthly usage') }}</x-ui.button>
                        </form>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @forelse ($usageRows as $row)
                            <div class="rounded-md border border-neutral-200 p-3">
                                <p class="text-xs text-neutral-400">{{ $row['label'] }} · {{ $row['period'] === 'month' ? __('this month') : __('total') }}</p>
                                <p class="text-sm font-semibold text-neutral-950">
                                    {{ number_format($row['used']) }} / {{ $row['maximum'] === null ? __('Unlimited') : number_format($row['maximum']) }}
                                    @if ($row['maximum'] !== null)
                                        <span class="text-xs {{ $row['percent'] >= 100 ? 'text-error' : ($row['percent'] >= 80 ? 'text-warning' : 'text-neutral-400') }}">({{ $row['percent'] }}%)</span>
                                    @endif
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-neutral-400">{{ __('This plan has no numeric limits.') }}</p>
                        @endforelse
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="heading-5 text-neutral-950">{{ __('History') }}</h2>
                    <ol class="mt-4 space-y-3">
                        @forelse ($subscription->events as $event)
                            <li class="flex flex-wrap items-start justify-between gap-2 border-b border-neutral-100 pb-3 text-sm last:border-0">
                                <div>
                                    <p class="font-semibold text-neutral-950">
                                        {{ $event->type->label() }}
                                        @if ($event->fromPlan && $event->toPlan && $event->from_plan_id !== $event->to_plan_id)
                                            <span class="font-normal text-neutral-600">{{ $event->fromPlan->name }} → {{ $event->toPlan->name }}</span>
                                        @elseif ($event->toPlan)
                                            <span class="font-normal text-neutral-600">{{ $event->toPlan->name }}</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-neutral-400">
                                        {{ $event->actor?->name ?? __('System') }}
                                        @if ($event->payment) · {{ $event->payment->formatted_amount }} ({{ $event->payment->gateway }}) @endif
                                        @if (data_get($event->meta, 'days')) · {{ __('+:days days', ['days' => data_get($event->meta, 'days')]) }} @endif
                                        @if (data_get($event->meta, 'key')) · {{ \App\Modules\PlansSubscriptions\Services\PlanLimitService::label(data_get($event->meta, 'key')) }} {{ data_get($event->meta, 'threshold') }}% @endif
                                        @if (data_get($event->meta, 'note')) · {{ data_get($event->meta, 'note') }} @endif
                                    </p>
                                </div>
                                <span class="text-xs text-neutral-400">{{ $event->created_at->format('M d, Y H:i') }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-neutral-400">{{ __('No history yet.') }}</li>
                        @endforelse
                    </ol>
                </div>

                <div class="section-card">
                    <h2 class="heading-5 text-neutral-950">{{ __('Plan payments') }}</h2>
                    <x-tables.table>
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Description') }}</th>
                                <th>{{ __('Gateway') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td data-th="{{ __('Date') }}" class="text-sm">{{ ($payment->paid_at ?? $payment->created_at)->format('M d, Y') }}</td>
                                    <td data-th="{{ __('Description') }}" class="text-sm">
                                        <a href="{{ route('admin.payments.show', $payment) }}" class="text-primary hover:underline">{{ $payment->description ?: __('Payment') }}</a>
                                        @if (data_get($payment->metadata, 'invoice_number'))
                                            <span class="block text-xs text-neutral-400">{{ data_get($payment->metadata, 'invoice_number') }}</span>
                                        @endif
                                    </td>
                                    <td data-th="{{ __('Gateway') }}" class="text-sm">{{ ucfirst($payment->gateway) }}</td>
                                    <td data-th="{{ __('Amount') }}" class="text-sm font-semibold">{{ $payment->formatted_amount }}</td>
                                    <td data-th="{{ __('Status') }}"><x-ui.badge :variant="$payment->status === 'completed' ? 'success' : 'neutral'">{{ ucfirst($payment->status) }}</x-ui.badge></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="table-empty-state">{{ __('No plan payments yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </x-tables.table>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="section-card">
                    <h2 class="heading-5 text-neutral-950">{{ __('Change plan') }}</h2>
                    <p class="mt-1 text-xs text-neutral-400">{{ __('Applies immediately without charging the customer.') }}</p>
                    <form method="POST" action="{{ route('admin.subscriptions.change-plan', $subscription) }}" class="mt-4 space-y-4">
                        @csrf
                        <x-forms.select :label="__('Plan')" name="plan_id" required :selected="$subscription->plan_id" :options="$plans->mapWithKeys(fn ($plan) => [$plan->id => $plan->name.($plan->is_active ? '' : ' ('.__('inactive').')')])->all()" />
                        <x-forms.toggle :label="__('Start a new billing period today')" name="restart_period" :checked="true" />
                        <x-forms.input :label="__('Note')" name="note" :placeholder="__('Optional, shown in history')" />
                        <x-ui.button type="submit" variant="primary" class="w-full">{{ __('Change plan') }}</x-ui.button>
                    </form>
                </div>

                <div class="section-card">
                    <h2 class="heading-5 text-neutral-950">{{ __('Extend') }}</h2>
                    <p class="mt-1 text-xs text-neutral-400">{{ __('Adds free days. Reactivates an expired subscription.') }}</p>
                    <form method="POST" action="{{ route('admin.subscriptions.extend', $subscription) }}" class="mt-4 space-y-4">
                        @csrf
                        <x-forms.input :label="__('Days')" name="days" type="number" min="1" max="3650" required value="30" />
                        <x-forms.input :label="__('Note')" name="note" :placeholder="__('Optional, shown in history')" />
                        <x-ui.button type="submit" variant="primary" class="w-full">{{ __('Extend subscription') }}</x-ui.button>
                    </form>
                </div>

                <div class="section-card space-y-3">
                    <h2 class="heading-5 text-neutral-950">{{ __('Cancel or resume') }}</h2>
                    @if ($subscription->cancel_at_period_end || $subscription->pending_plan_id || $status === 'cancelled')
                        <form method="POST" action="{{ route('admin.subscriptions.resume', $subscription) }}">
                            @csrf
                            <x-ui.button type="submit" variant="outline" class="w-full">{{ __('Resume / undo scheduled change') }}</x-ui.button>
                        </form>
                    @endif
                    @if ($isUsable)
                        <form method="POST" action="{{ route('admin.subscriptions.cancel', $subscription) }}">
                            @csrf
                            <input type="hidden" name="at_period_end" value="1">
                            <x-ui.button type="submit" variant="outline" class="w-full">{{ __('Cancel at period end') }}</x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('admin.subscriptions.cancel', $subscription) }}">
                            @csrf
                            <input type="hidden" name="at_period_end" value="0">
                            <x-ui.button type="submit" variant="outline" class="w-full text-error">{{ __('Cancel now (lock workspace)') }}</x-ui.button>
                        </form>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</x-layouts.admin>
