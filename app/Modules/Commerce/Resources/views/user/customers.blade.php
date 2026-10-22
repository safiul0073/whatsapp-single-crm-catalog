<x-layouts.user :title="__('Commerce customers')">
    <div class="space-y-6" x-data="{
        activeCustomer: null,
        loadingDetails: false,
        openDetails(customer) {
            this.activeCustomer = customer;
            this.loadingDetails = true;
            fetch('{{ route('user.commerce.customers.index') }}/' + customer.id, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                this.activeCustomer = data.customer;
                this.activeCustomer.orders = data.orders || [];
                this.loadingDetails = false;
            })
            .catch(() => {
                this.loadingDetails = false;
            });
        },
        closeDetails() {
            this.activeCustomer = null;
        }
    }">
        {{-- Header & Action Buttons --}}
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-wider uppercase text-primary">{{ __('Commerce Management') }}</p>
                <h1 class="heading-3 text-title font-bold">{{ __('Customers') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Customers registered and authenticated through Storefront and Mobile App.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.orders.index') }}" class="shadow-xs hover:border-primary hover:text-primary">
                    <i class="ph ph-package text-base"></i>
                    <span>{{ __('Orders') }}</span>
                </x-ui.button>
                <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.products.index') }}" class="shadow-xs hover:border-primary hover:text-primary">
                    <i class="ph ph-t-shirt text-base"></i>
                    <span>{{ __('Products') }}</span>
                </x-ui.button>
                @can('commerce.manage')
                    <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.pos.index') }}" class="shadow-xs hover:border-primary hover:text-primary">
                        <i class="ph ph-cash-register text-base"></i>
                        <span>{{ __('POS') }}</span>
                    </x-ui.button>
                    <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.storefront.index') }}" class="shadow-xs hover:border-primary hover:text-primary">
                        <i class="ph ph-browser text-base"></i>
                        <span>{{ __('Storefront Studio') }}</span>
                    </x-ui.button>
                @endcan
            </div>
        </header>

        {{-- Filters & Search Bar --}}
        <div class="card p-4 sm:p-5">
            <form method="GET" action="{{ route('user.commerce.customers.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                {{-- Search Box --}}
                <div class="relative flex-1">
                    <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400"></i>
                    <input
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="{{ __('Search customer name, phone, email, or city...') }}"
                        class="form-input input-search !pl-11 !pr-10 w-full py-2 text-sm rounded-lg border-neutral-200 bg-neutral-0 placeholder:text-neutral-400 focus:border-primary focus:ring-2 focus:ring-primary/20 [&::-webkit-search-cancel-button]:hidden [&::-webkit-search-decoration]:hidden"
                    />
                    @if(request('search'))
                        <a href="{{ request()->fullUrlWithQuery(['search' => null, 'page' => null]) }}" class="absolute top-1/2 right-3 -translate-y-1/2 flex items-center text-neutral-400 hover:text-neutral-600" title="{{ __('Clear search') }}">
                            <i class="ph ph-x text-sm"></i>
                        </a>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-sm btn-primary inline-flex items-center gap-1.5 shadow-xs">
                        <i class="ph ph-funnel text-base"></i>
                        <span>{{ __('Filter') }}</span>
                    </button>

                    @if(request()->filled('search'))
                        <a href="{{ route('user.commerce.customers.index') }}" class="btn btn-sm btn-outline inline-flex items-center gap-1.5 text-neutral-600 hover:text-error hover:border-error" title="{{ __('Clear search') }}">
                            <i class="ph ph-arrow-counter-clockwise text-base"></i>
                            <span>{{ __('Reset') }}</span>
                        </a>
                    @endif
                </div>
            </form>

            @if(request()->filled('search'))
                <div class="mt-3.5 pt-3 border-t border-border-soft flex items-center gap-2 text-xs">
                    <span class="text-neutral-500 font-medium">{{ __('Search query:') }}</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 border border-neutral-200 px-2.5 py-0.5 text-neutral-700">
                        <i class="ph ph-magnifying-glass text-neutral-400"></i>
                        <span>"{{ request('search') }}"</span>
                        <a href="{{ route('user.commerce.customers.index') }}" class="text-neutral-400 hover:text-error"><i class="ph ph-x"></i></a>
                    </span>
                    <a href="{{ route('user.commerce.customers.index') }}" class="text-xs text-primary hover:underline ml-1 font-semibold">{{ __('Clear search') }}</a>
                </div>
            @endif
        </div>

        {{-- Customers Table Section --}}
        <section class="card overflow-hidden p-0">
            {{-- Table Meta Header --}}
            <div class="flex flex-col gap-2 border-b border-border-soft px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between bg-neutral-50/50">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-title">{{ __('Customers list') }}</h2>
                    <span class="rounded-full bg-neutral-200/80 px-2 py-0.5 text-[11px] font-bold text-neutral-600">{{ $customers->total() }}</span>
                </div>
                <div class="text-xs text-body">
                    @if($customers->total() > 0)
                        {{ __('Showing') }} <span class="font-semibold text-title">{{ $customers->firstItem() }}</span>–<span class="font-semibold text-title">{{ $customers->lastItem() }}</span> {{ __('of') }} <span class="font-semibold text-title">{{ $customers->total() }}</span>
                    @else
                        {{ __('0 customers found') }}
                    @endif
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-border-soft bg-neutral-50/75 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                            <th class="py-3 px-4">{{ __('Customer') }}</th>
                            <th class="py-3 px-4">{{ __('Channel') }}</th>
                            <th class="py-3 px-4">{{ __('Status') }}</th>
                            <th class="py-3 px-4">{{ __('Orders & Lifetime spend') }}</th>
                            <th class="py-3 px-4">{{ __('Default shipping address') }}</th>
                            <th class="py-3 px-4">{{ __('Registered') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        @forelse($customers as $customer)
                            @php
                                $contact = $customer->contact;
                                $name = $contact?->name ?: __('Guest Customer');
                                $phone = $customer->verified_phone ?: ($contact?->phone ?: '—');
                                $email = $contact?->email;
                                $initial = mb_substr($name, 0, 1);

                                $defaultAddr = $customer->addresses->firstWhere('is_default', true) ?? $customer->addresses->first();
                                $addrCity = $defaultAddr?->address['city'] ?? $contact?->city;
                                $addrCountry = $defaultAddr?->address['country'] ?? $contact?->country;

                                $ordersCount = $customer->orders_count ?? 0;
                                $contactSpend = $spending->get($customer->contact_id, collect());
                                $spendSummary = $contactSpend->map(function ($row) {
                                    $currency = strtoupper((string) ($row->currency ?? 'USD'));
                                    $symbol = match ($currency) {
                                        'USD' => '$',
                                        'BDT' => '৳',
                                        'EUR' => '€',
                                        'GBP' => '£',
                                        'INR' => '₹',
                                        default => $currency . ' ',
                                    };
                                    return $symbol . number_format((float) $row->total_spent, 2);
                                })->implode(', ');
                            @endphp
                            <tr class="hover:bg-neutral-50/70 transition-colors">
                                {{-- Customer Identity --}}
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary border border-primary/20 uppercase shrink-0">
                                            {{ $initial }}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="font-medium text-title truncate" title="{{ $name }}">{{ $name }}</div>
                                            <div class="text-xs text-neutral-500 font-mono flex items-center gap-1.5 mt-0.5">
                                                <span>{{ $phone }}</span>
                                                @if($email)
                                                    <span class="text-neutral-300">·</span>
                                                    <span class="truncate max-w-[160px]" title="{{ $email }}">{{ $email }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Channel / Source --}}
                                <td class="py-3 px-4">
                                    @if($customer->source === 'mobile_app')
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-purple-200 bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">
                                            <i class="ph ph-device-mobile text-sm"></i>
                                            <span>{{ __('Mobile App') }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                            <i class="ph ph-globe text-sm"></i>
                                            <span>{{ __('Storefront') }}</span>
                                        </span>
                                    @endif
                                </td>

                                {{-- Status / Verification --}}
                                <td class="py-3 px-4">
                                    @if($customer->verified_at)
                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700" title="{{ __('Verified at') }}: {{ $customer->verified_at->format('M j, Y') }}">
                                            <i class="ph ph-check-circle text-sm text-emerald-600"></i>
                                            <span>{{ __('Verified') }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600">
                                            <i class="ph ph-circle text-xs text-neutral-400"></i>
                                            <span>{{ __('Unverified') }}</span>
                                        </span>
                                    @endif
                                </td>

                                {{-- Orders & Spend --}}
                                <td class="py-3 px-4">
                                    <div class="text-xs">
                                        @if($ordersCount > 0)
                                            <div class="font-semibold text-title">
                                                {{ trans_choice(':count order|:count orders', $ordersCount, ['count' => $ordersCount]) }}
                                            </div>
                                            <div class="text-emerald-700 font-medium mt-0.5">
                                                {{ $spendSummary ?: __('No paid total') }}
                                            </div>
                                        @else
                                            <span class="text-neutral-400">{{ __('No orders yet') }}</span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Default Address --}}
                                <td class="py-3 px-4">
                                    @if($addrCity || $addrCountry)
                                        <div class="text-xs font-medium text-title">
                                            {{ implode(', ', array_filter([$addrCity, $addrCountry])) }}
                                        </div>
                                        @if($defaultAddr && !empty($defaultAddr->address['line1']))
                                            <div class="text-[11px] text-neutral-400 truncate max-w-[180px]" title="{{ $defaultAddr->address['line1'] }}">
                                                {{ $defaultAddr->address['line1'] }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-neutral-300">—</span>
                                    @endif
                                </td>

                                {{-- Registered Date --}}
                                <td class="py-3 px-4">
                                    <div class="text-xs font-medium text-title" title="{{ $customer->registered_at?->format('M j, Y · g:i A') ?? $customer->created_at->format('M j, Y · g:i A') }}">
                                        {{ ($customer->registered_at ?? $customer->created_at)->diffForHumans() }}
                                    </div>
                                    <div class="text-[11px] text-neutral-400">
                                        {{ ($customer->registered_at ?? $customer->created_at)->format('M j, Y') }}
                                    </div>
                                </td>

                                {{-- Actions --}}
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        {{-- View Customer Orders --}}
                                        @if($customer->contact_id)
                                            <a href="{{ route('user.commerce.orders.index', ['contact_id' => $customer->contact_id]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-200 bg-neutral-0 text-neutral-600 hover:border-primary hover:text-primary transition shadow-2xs" title="{{ __('View customer orders') }}">
                                                <i class="ph ph-shopping-bag text-base"></i>
                                                <span class="sr-only">{{ __('View orders') }}</span>
                                            </a>
                                        @endif

                                        {{-- Open Details Drawer --}}
                                        <button
                                            type="button"
                                            @click="openDetails({{ Js::from([
                                                'id' => $customer->id,
                                                'name' => $name,
                                                'phone' => $phone,
                                                'email' => $email,
                                                'verified_at' => $customer->verified_at?->format('M j, Y · g:i A'),
                                                'registered_at' => ($customer->registered_at ?? $customer->created_at)->format('M j, Y · g:i A'),
                                                'source' => $customer->source,
                                                'channel_label' => $customer->channelLabel(),
                                                'contact_id' => $customer->contact_id,
                                                'orders_count' => $ordersCount,
                                                'spend_summary' => $spendSummary,
                                                'addresses' => $customer->addresses->map(fn($a) => [
                                                    'label' => $a->label,
                                                    'is_default' => $a->is_default,
                                                    'address' => $a->address,
                                                ])->values()->all(),
                                            ]) }})"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-200 bg-neutral-0 text-neutral-600 hover:border-primary hover:text-primary transition shadow-2xs"
                                            title="{{ __('View customer details') }}"
                                        >
                                            <i class="ph ph-eye text-base"></i>
                                            <span class="sr-only">{{ __('View details') }}</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-12 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-neutral-100 text-neutral-400 mb-3">
                                        <i class="ph ph-users text-2xl"></i>
                                    </div>
                                    <h3 class="text-base font-semibold text-title">{{ __('No customers found') }}</h3>
                                    <p class="mt-1 text-sm text-body max-w-sm mx-auto">
                                        @if(request()->filled('search'))
                                            {{ __('No customers matched your search query. Try searching with a different name, phone, or email.') }}
                                        @else
                                            {{ __('Customers who register or sign in on the Storefront or Mobile App will appear here automatically.') }}
                                        @endif
                                    </p>
                                    @if(request()->filled('search'))
                                        <div class="mt-4">
                                            <a href="{{ route('user.commerce.customers.index') }}" class="btn btn-sm btn-outline inline-flex items-center gap-1.5">
                                                <i class="ph ph-arrow-counter-clockwise"></i>
                                                <span>{{ __('Clear search') }}</span>
                                            </a>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination Footer --}}
            @if($customers->hasPages())
                <div class="border-t border-border-soft px-5 py-4">
                    {{ $customers->links() }}
                </div>
            @endif
        </section>

        {{-- Slide-over Drawer / Modal for Customer Details --}}
        <div
            x-show="activeCustomer !== null"
            x-cloak
            class="fixed inset-0 z-50 overflow-hidden"
            aria-labelledby="slide-over-title"
            role="dialog"
            aria-modal="true"
        >
            {{-- Backdrop --}}
            <div
                x-show="activeCustomer !== null"
                x-transition:enter="ease-in-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in-out duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeDetails()"
                class="fixed inset-0 bg-neutral-900/40 backdrop-blur-xs transition-opacity"
            ></div>

            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div
                    x-show="activeCustomer !== null"
                    x-transition:enter="transform transition ease-in-out duration-300"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-200"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="pointer-events-auto w-screen max-w-md bg-neutral-0 shadow-2xl flex flex-col"
                >
                    {{-- Drawer Header --}}
                    <div class="flex items-center justify-between border-b border-border-soft px-5 py-4 bg-neutral-50/60">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary border border-primary/20 uppercase shrink-0" x-text="activeCustomer ? activeCustomer.name.charAt(0) : ''"></span>
                            <div>
                                <h3 class="text-base font-semibold text-title" x-text="activeCustomer ? activeCustomer.name : ''"></h3>
                                <p class="text-xs text-neutral-500 font-mono" x-text="activeCustomer ? activeCustomer.phone : ''"></p>
                            </div>
                        </div>
                        <button type="button" @click="closeDetails()" class="rounded-md p-1.5 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 transition">
                            <i class="ph ph-x text-lg"></i>
                        </button>
                    </div>

                    {{-- Drawer Body --}}
                    <div class="flex-1 overflow-y-auto p-5 space-y-6">
                        {{-- Account Summary Card --}}
                        <div class="rounded-lg border border-border-soft p-4 bg-neutral-50/40 space-y-3">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ __('Account Details') }}</h4>
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <span class="text-neutral-400 block">{{ __('Registration Channel') }}</span>
                                    <span class="font-medium text-title inline-flex items-center gap-1 mt-0.5" x-text="activeCustomer ? activeCustomer.channel_label : ''"></span>
                                </div>
                                <div>
                                    <span class="text-neutral-400 block">{{ __('Phone / WhatsApp') }}</span>
                                    <span class="font-medium text-title inline-flex items-center gap-1 mt-0.5">
                                        <template x-if="activeCustomer && activeCustomer.verified_at">
                                            <span class="text-emerald-600 font-semibold flex items-center gap-1"><i class="ph ph-check-circle"></i> Verified</span>
                                        </template>
                                        <template x-if="activeCustomer && !activeCustomer.verified_at">
                                            <span class="text-neutral-500">Unverified</span>
                                        </template>
                                    </span>
                                </div>
                                <div class="col-span-2" x-show="activeCustomer && activeCustomer.email">
                                    <span class="text-neutral-400 block">{{ __('Email Address') }}</span>
                                    <span class="font-medium text-title" x-text="activeCustomer ? activeCustomer.email : ''"></span>
                                </div>
                                <div>
                                    <span class="text-neutral-400 block">{{ __('Joined Date') }}</span>
                                    <span class="font-medium text-title" x-text="activeCustomer ? activeCustomer.registered_at : ''"></span>
                                </div>
                                <div>
                                    <span class="text-neutral-400 block">{{ __('Orders Placed') }}</span>
                                    <span class="font-medium text-title" x-text="activeCustomer ? activeCustomer.orders_count + ' orders' : '0 orders'"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Saved Addresses --}}
                        <div class="space-y-3">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ __('Shipping Addresses') }}</h4>
                            <template x-if="!activeCustomer || !activeCustomer.addresses || activeCustomer.addresses.length === 0">
                                <p class="text-xs text-neutral-400 italic">{{ __('No addresses saved yet.') }}</p>
                            </template>
                            <template x-for="(addr, idx) in (activeCustomer ? activeCustomer.addresses : [])" :key="idx">
                                <div class="rounded-lg border border-border-soft p-3 text-xs space-y-1 bg-neutral-0">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-title" x-text="addr.label || 'Address'"></span>
                                        <template x-if="addr.is_default">
                                            <span class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary">{{ __('Default') }}</span>
                                        </template>
                                    </div>
                                    <p class="text-neutral-600" x-text="[addr.address.line1, addr.address.line2].filter(Boolean).join(', ')"></p>
                                    <p class="text-neutral-500" x-text="[addr.address.city, addr.address.state, addr.address.postal_code, addr.address.country].filter(Boolean).join(', ')"></p>
                                </div>
                            </template>
                        </div>

                        {{-- Recent Orders --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ __('Recent Orders') }}</h4>
                                <template x-if="activeCustomer && activeCustomer.contact_id">
                                    <a :href="'{{ route('user.commerce.orders.index') }}?contact_id=' + activeCustomer.contact_id" class="text-xs font-semibold text-primary hover:underline">
                                        {{ __('View all in orders') }} →
                                    </a>
                                </template>
                            </div>

                            <template x-if="loadingDetails">
                                <div class="py-6 text-center text-xs text-neutral-400">
                                    <i class="ph ph-spinner animate-spin text-lg mb-1"></i>
                                    <p>{{ __('Loading orders...') }}</p>
                                </div>
                            </template>

                            <template x-if="!loadingDetails && (!activeCustomer || !activeCustomer.orders || activeCustomer.orders.length === 0)">
                                <p class="text-xs text-neutral-400 italic">{{ __('No orders placed yet.') }}</p>
                            </template>

                            <template x-if="!loadingDetails && activeCustomer && activeCustomer.orders && activeCustomer.orders.length > 0">
                                <div class="space-y-2">
                                    <template x-for="ord in activeCustomer.orders" :key="ord.id">
                                        <a :href="'{{ url('dashboard/commerce/orders') }}/' + ord.id" class="block rounded-lg border border-border-soft p-3 hover:border-primary transition bg-neutral-0">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="font-mono font-semibold text-primary" x-text="ord.number"></span>
                                                <span class="font-bold text-title" x-text="((ord.currency === 'USD' || !ord.currency) ? '$' : ord.currency + ' ') + Number(ord.total || ord.subtotal || 0).toFixed(2)"></span>
                                            </div>
                                            <div class="mt-1 flex items-center justify-between text-[11px] text-neutral-400">
                                                <span class="capitalize" x-text="(ord.status || '').replace('_', ' ')"></span>
                                                <span x-text="ord.created_at ? new Date(ord.created_at).toLocaleDateString() : ''"></span>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Drawer Footer --}}
                    <div class="border-t border-border-soft p-4 bg-neutral-50/50 flex items-center justify-end gap-2">
                        <template x-if="activeCustomer && activeCustomer.contact_id">
                            <a :href="'{{ route('user.commerce.orders.index') }}?contact_id=' + activeCustomer.contact_id" class="btn btn-sm btn-primary inline-flex items-center gap-1.5 shadow-xs">
                                <i class="ph ph-shopping-bag"></i>
                                <span>{{ __('View customer orders') }}</span>
                            </a>
                        </template>
                        <button type="button" @click="closeDetails()" class="btn btn-sm btn-outline">
                            {{ __('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.user>
