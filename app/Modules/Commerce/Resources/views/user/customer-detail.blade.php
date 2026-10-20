<x-layouts.user :title="$customer->contact?->name ?? __('Customer details')">
    <div class="space-y-6">
        {{-- Header --}}
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('user.commerce.customers.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-200 bg-neutral-0 text-neutral-600 hover:border-primary hover:text-primary transition shadow-2xs">
                    <i class="ph ph-arrow-left text-base"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="heading-3 text-title font-bold">{{ $customer->contact?->name ?: __('Guest Customer') }}</h1>
                        @if($customer->source === 'mobile_app')
                            <span class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700">
                                <i class="ph ph-device-mobile"></i> {{ __('Mobile App') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                <i class="ph ph-globe"></i> {{ __('Storefront') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-neutral-500 font-mono mt-0.5">
                        {{ $customer->verified_phone ?: ($customer->contact?->phone ?: '—') }}
                        @if($customer->contact?->email)
                            · {{ $customer->contact->email }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if($customer->contact_id)
                    <x-ui.button variant="primary" size="sm" href="{{ route('user.commerce.orders.index', ['contact_id' => $customer->contact_id]) }}" class="shadow-xs">
                        <i class="ph ph-shopping-bag text-base"></i>
                        <span>{{ __('View orders') }}</span>
                    </x-ui.button>
                @endif
                <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.customers.index') }}">
                    <span>{{ __('Back to customers') }}</span>
                </x-ui.button>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left Column: Account Details & Saved Addresses --}}
            <div class="space-y-6">
                {{-- Account Profile Card --}}
                <div class="card p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-title border-b border-border-soft pb-3">{{ __('Account Profile') }}</h2>
                    <div class="space-y-3 text-xs">
                        <div>
                            <span class="text-neutral-400 block">{{ __('Full name') }}</span>
                            <span class="font-medium text-title text-sm">{{ $customer->contact?->name ?: '—' }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400 block">{{ __('Phone number') }}</span>
                            <span class="font-medium text-title font-mono">{{ $customer->verified_phone ?: ($customer->contact?->phone ?: '—') }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400 block">{{ __('Email address') }}</span>
                            <span class="font-medium text-title">{{ $customer->contact?->email ?: '—' }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400 block">{{ __('Verification status') }}</span>
                            @if($customer->verified_at)
                                <span class="inline-flex items-center gap-1 text-emerald-600 font-semibold mt-0.5">
                                    <i class="ph ph-check-circle text-sm"></i> {{ __('Verified on') }} {{ $customer->verified_at->format('M j, Y · g:i A') }}
                                </span>
                            @else
                                <span class="text-neutral-500">{{ __('Unverified') }}</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-neutral-400 block">{{ __('Member since') }}</span>
                            <span class="font-medium text-title">{{ ($customer->registered_at ?? $customer->created_at)->format('M j, Y · g:i A') }}</span>
                        </div>
                        @if($customer->last_login_at)
                            <div>
                                <span class="text-neutral-400 block">{{ __('Last login') }}</span>
                                <span class="font-medium text-title">{{ $customer->last_login_at->diffForHumans() }} ({{ str($customer->last_login_channel ?? 'storefront')->replace('_', ' ')->title() }})</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Saved Addresses Card --}}
                <div class="card p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-title border-b border-border-soft pb-3">{{ __('Saved Addresses') }}</h2>
                    @forelse($customer->addresses as $addr)
                        <div class="rounded-lg border border-border-soft p-3.5 text-xs space-y-1 bg-neutral-50/40">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-title">{{ $addr->label ?: __('Shipping address') }}</span>
                                @if($addr->is_default)
                                    <span class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary">{{ __('Default') }}</span>
                                @endif
                            </div>
                            <p class="text-neutral-700">{{ implode(', ', array_filter([$addr->address['line1'] ?? null, $addr->address['line2'] ?? null])) }}</p>
                            <p class="text-neutral-500">{{ implode(', ', array_filter([$addr->address['city'] ?? null, $addr->address['state'] ?? null, $addr->address['postal_code'] ?? null, $addr->address['country'] ?? null])) }}</p>
                        </div>
                    @empty
                        <p class="text-xs text-neutral-400 italic">{{ __('No addresses saved for this customer yet.') }}</p>
                    @endforelse
                </div>
            </div>

            {{-- Right Column: Order History --}}
            <div class="lg:col-span-2">
                <div class="card p-0 overflow-hidden">
                    <div class="border-b border-border-soft px-5 py-4 bg-neutral-50/50 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-title">{{ __('Order History') }}</h2>
                            <p class="text-xs text-body">{{ trans_choice(':count order placed|:count orders placed', $orders->count(), ['count' => $orders->count()]) }}</p>
                        </div>
                        @if($customer->contact_id)
                            <a href="{{ route('user.commerce.orders.index', ['contact_id' => $customer->contact_id]) }}" class="text-xs font-semibold text-primary hover:underline">
                                {{ __('View in orders table') }} →
                            </a>
                        @endif
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="border-b border-border-soft bg-neutral-50/75 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                                    <th class="py-3 px-4">{{ __('Order #') }}</th>
                                    <th class="py-3 px-4">{{ __('Status') }}</th>
                                    <th class="py-3 px-4 text-right">{{ __('Total') }}</th>
                                    <th class="py-3 px-4">{{ __('Date') }}</th>
                                    <th class="py-3 px-4 text-right">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-soft text-xs">
                                @forelse($orders as $ord)
                                    <tr class="hover:bg-neutral-50/70 transition-colors">
                                        <td class="py-3 px-4">
                                            <a href="{{ route('user.commerce.orders.show', $ord) }}" class="font-mono font-semibold text-primary hover:underline">
                                                {{ $ord->number }}
                                            </a>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium bg-neutral-100 text-neutral-700">
                                                {{ str($ord->status)->replace('_', ' ')->title() }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right font-semibold text-title">
                                            {{ $ord->currency }} {{ number_format((float) ($ord->total ?? $ord->subtotal ?? 0), 2) }}
                                        </td>
                                        <td class="py-3 px-4 text-neutral-500">
                                            {{ $ord->created_at?->format('M j, Y') }}
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <a href="{{ route('user.commerce.orders.show', $ord) }}" class="text-primary hover:underline font-semibold">
                                                {{ __('View') }} →
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-10 text-center text-xs text-neutral-400">
                                            {{ __('No orders placed by this customer yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.user>
