<x-layouts.admin :title="__('Subscriptions')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Subscriptions') }}</h1>
        </div>

        <div class="section-card">
            <x-tables.datatable :url="route('admin.subscriptions.index')" :searchable="false" :perPageOptions="[]">
                <x-tables.table>
                    <thead>
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Workspace') }}</th>
                            <th>{{ __('Plan') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Period') }}</th>
                            <th>{{ __('Usage') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $subscription)
                            @php
                                $owner = $subscription->workspace?->owner;
                                $status = $subscription->status?->value ?? (string) $subscription->status;
                            @endphp
                            <tr>
                                <td data-th="{{ __('User') }}">
                                    <div class="text-right lg:text-left">
                                        <p class="text-sm font-bold text-neutral-950">{{ $owner?->name ?? __('Unknown user') }}</p>
                                        <p class="text-xs text-neutral-600">{{ $owner?->email ?? __('No email') }}</p>
                                    </div>
                                </td>
                                <td data-th="{{ __('Workspace') }}">
                                    <p class="text-sm font-medium text-neutral-900">{{ $subscription->workspace?->name ?? __('Deleted workspace') }}</p>
                                    <p class="text-xs text-neutral-400">{{ $subscription->workspace?->slug }}</p>
                                </td>
                                <td data-th="{{ __('Plan') }}">
                                    <p class="text-sm font-medium text-neutral-900">{{ $subscription->plan?->name ?? __('No plan') }}</p>
                                    @if ($subscription->plan)
                                        <p class="text-xs text-neutral-400">{{ currency_format($subscription->plan->price) }} · {{ ucfirst($subscription->plan->interval) }}</p>
                                    @endif
                                </td>
                                <td data-th="{{ __('Status') }}">
                                    <div class="flex justify-end lg:justify-start rtl:justify-start">
                                        <x-ui.badge variant="info">{{ ucfirst(str_replace('_', ' ', $status)) }}</x-ui.badge>
                                    </div>
                                </td>
                                <td data-th="{{ __('Period') }}" class="text-sm text-neutral-600">
                                    <p>{{ __('Starts') }}: {{ $subscription->starts_at?->format('M d, Y') ?? __('Not set') }}</p>
                                    <p>{{ __('Renews') }}: {{ $subscription->renews_at?->format('M d, Y') ?? __('Not set') }}</p>
                                    @if ($subscription->ends_at)
                                        <p>{{ __('Ends') }}: {{ $subscription->ends_at->format('M d, Y') }}</p>
                                    @endif
                                </td>
                                <td data-th="{{ __('Usage') }}">
                                    <div class="flex max-w-xs flex-wrap justify-end gap-1.5 lg:justify-start">
                                        @forelse (($subscription->usage ?? []) as $key => $value)
                                            <x-ui.badge variant="neutral">{{ str_replace('_', ' ', $key) }}: {{ is_numeric($value) ? number_format((float) $value) : $value }}</x-ui.badge>
                                        @empty
                                            <span class="text-sm text-neutral-400">{{ __('No usage recorded') }}</span>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="table-empty-state">{{ __('No subscriptions found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-tables.table>

                <x-slot:pagination>
                    <x-tables.pagination :paginator="$subscriptions" />
                </x-slot:pagination>
            </x-tables.datatable>
        </div>
    </div>
</x-layouts.admin>
