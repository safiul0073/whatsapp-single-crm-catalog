<x-layouts.user :title="__('Commerce orders')">
    @php
        $currentStatus = request('status', '');
        $awaitingCount = ($statusCounts->get('awaiting_payment', 0) ?? 0) + ($statusCounts->get('quoted', 0) ?? 0) + ($statusCounts->get('needs_details', 0) ?? 0);
        $processingCount = ($statusCounts->get('processing', 0) ?? 0) + ($statusCounts->get('packed', 0) ?? 0);
        $completedCount = ($statusCounts->get('completed', 0) ?? 0) + ($statusCounts->get('delivered', 0) ?? 0);
        $tabs = [
            ['id' => '', 'label' => __('All Orders'), 'count' => $totalCount, 'active' => empty($currentStatus)],
            ['id' => 'paid', 'label' => __('Paid'), 'count' => $statusCounts->get('paid', 0) ?? 0, 'active' => $currentStatus === 'paid'],
            ['id' => 'awaiting_payment', 'label' => __('Awaiting Payment'), 'count' => $awaitingCount, 'active' => $currentStatus === 'awaiting_payment'],
            ['id' => 'processing', 'label' => __('Processing'), 'count' => $processingCount, 'active' => $currentStatus === 'processing'],
            ['id' => 'shipped', 'label' => __('Shipped'), 'count' => $statusCounts->get('shipped', 0) ?? 0, 'active' => $currentStatus === 'shipped'],
            ['id' => 'completed', 'label' => __('Completed'), 'count' => $completedCount, 'active' => $currentStatus === 'completed'],
            ['id' => 'cancelled', 'label' => __('Cancelled'), 'count' => $statusCounts->get('cancelled', 0) ?? 0, 'active' => $currentStatus === 'cancelled'],
        ];
    @endphp

    <div class="space-y-6">
        {{-- Header & Action Buttons --}}
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-wider uppercase text-primary">{{ __('Commerce Management') }}</p>
                <h1 class="heading-3 text-title font-bold">{{ __('Orders') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Review cart requests, prepare shipping quotes, track fulfillment, and manage sales.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                @can('commerce.pos.use')
                    <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.pos.index') }}" class="shadow-xs hover:border-primary hover:text-primary">
                        <i class="ph ph-cash-register text-base"></i>
                        <span>{{ __('POS') }}</span>
                    </x-ui.button>
                @endcan
                @can('commerce.orders.settings')
                    <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.payment-services.index') }}" class="shadow-xs hover:border-primary hover:text-primary">
                        <i class="ph ph-credit-card text-base"></i>
                        <span>{{ __('Payment Services') }}</span>
                    </x-ui.button>
                    <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.orders.settings') }}" class="shadow-xs hover:border-primary hover:text-primary">
                        <i class="ph ph-gear text-base"></i>
                        <span>{{ __('Order settings') }}</span>
                    </x-ui.button>
                @endcan
                <x-ui.button variant="outline" size="sm" href="{{ route('user.commerce.products.index') }}" class="shadow-xs hover:border-primary hover:text-primary">
                    <i class="ph ph-t-shirt text-base"></i>
                    <span>{{ __('Products') }}</span>
                </x-ui.button>
                @can('commerce.orders.create')
                    <x-ui.button variant="primary" size="sm" href="{{ route('user.commerce.orders.create') }}" class="shadow-xs">
                        <i class="ph ph-plus-circle text-base"></i>
                        <span>{{ __('Create order') }}</span>
                    </x-ui.button>
                @endcan
            </div>
        </header>

        {{-- Status Filter Tabs --}}
        <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar border-b border-border-soft">
            @foreach($tabs as $tab)
                <a
                    href="{{ request()->fullUrlWithQuery(['status' => $tab['id'] ?: null, 'page' => null]) }}"
                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-lg px-3.5 py-2 text-xs font-semibold transition-all duration-150 {{ $tab['active'] ? 'bg-primary text-neutral-0 shadow-xs' : 'bg-neutral-0 text-neutral-600 hover:bg-neutral-100 hover:text-title border border-border-soft' }}"
                >
                    <span>{{ $tab['label'] }}</span>
                    <span class="inline-flex h-4 min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-[10px] font-bold {{ $tab['active'] ? 'bg-white/20 text-white' : 'bg-neutral-100 text-neutral-500' }}">
                        {{ $tab['count'] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Filters & Search Bar --}}
        <div class="card p-4 sm:p-5">
            <form method="GET" action="{{ route('user.commerce.orders.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
                @if(request()->filled('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                @if(request()->filled('contact_id'))
                    <input type="hidden" name="contact_id" value="{{ request('contact_id') }}">
                @endif

                {{-- Search Box --}}
                <div class="relative flex-1">
                    <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400"></i>
                    <input
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="{{ __('Search order #, customer, tracking code, phone...') }}"
                        class="form-input input-search !pl-11 !pr-10 w-full py-2 text-sm rounded-lg border-neutral-200 bg-neutral-0 placeholder:text-neutral-400 focus:border-primary focus:ring-2 focus:ring-primary/20 [&::-webkit-search-cancel-button]:hidden [&::-webkit-search-decoration]:hidden"
                    />
                    @if(request('search'))
                        <a href="{{ request()->fullUrlWithQuery(['search' => null, 'page' => null]) }}" class="absolute top-1/2 right-3 -translate-y-1/2 flex items-center text-neutral-400 hover:text-neutral-600" title="{{ __('Clear search') }}">
                            <i class="ph ph-x text-sm"></i>
                        </a>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    {{-- Source Dropdown --}}
                    <div class="min-w-[160px] flex-1 sm:flex-initial">
                        <select name="source" class="form-input py-2 text-sm rounded-lg border-neutral-200 bg-neutral-0 focus:border-primary focus:ring-2 focus:ring-primary/20" onchange="this.form.submit()">
                            <option value="">{{ __('All sources') }}</option>
                            @foreach($sources as $src)
                                @php
                                    $srcLabel = match($src) {
                                        'storefront' => __('Storefront Checkout'),
                                        'pos' => __('POS Terminal'),
                                        'native_whatsapp' => __('Native WhatsApp'),
                                        'manual' => __('Manual Entry'),
                                        default => str($src)->replace('_', ' ')->title(),
                                    };
                                @endphp
                                <option value="{{ $src }}" @selected(request('source') === $src)>{{ $srcLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Payment State Dropdown --}}
                    <div class="min-w-[160px] flex-1 sm:flex-initial">
                        <select name="payment_state" class="form-input py-2 text-sm rounded-lg border-neutral-200 bg-neutral-0 focus:border-primary focus:ring-2 focus:ring-primary/20" onchange="this.form.submit()">
                            <option value="">{{ __('All payment states') }}</option>
                            @foreach(['unpaid' => __('Unpaid'), 'partially_paid' => __('Partially Paid'), 'paid' => __('Paid'), 'submitted' => __('Submitted')] as $stateKey => $stateLabel)
                                <option value="{{ $stateKey }}" @selected(request('payment_state') === $stateKey)>{{ $stateLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Action Buttons --}}
                    <button type="submit" class="btn btn-sm btn-primary inline-flex items-center gap-1.5 shadow-xs">
                        <i class="ph ph-funnel text-base"></i>
                        <span>{{ __('Filter') }}</span>
                    </button>

                    @if(request()->anyFilled(['search', 'status', 'source', 'payment_state', 'contact_id', 'from', 'to']))
                        <a href="{{ route('user.commerce.orders.index') }}" class="btn btn-sm btn-outline inline-flex items-center gap-1.5 text-neutral-600 hover:text-error hover:border-error" title="{{ __('Reset all filters') }}">
                            <i class="ph ph-arrow-counter-clockwise text-base"></i>
                            <span>{{ __('Reset') }}</span>
                        </a>
                    @endif
                </div>
            </form>

            {{-- Active Filter Tags --}}
            @if(request()->anyFilled(['search', 'status', 'source', 'payment_state', 'contact_id']))
                <div class="mt-3.5 pt-3 border-t border-border-soft flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-neutral-500 font-medium">{{ __('Active filters:') }}</span>
                    @if(request('search'))
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 border border-neutral-200 px-2.5 py-0.5 text-neutral-700">
                            <i class="ph ph-magnifying-glass text-neutral-400"></i>
                            <span>"{{ request('search') }}"</span>
                            <a href="{{ request()->fullUrlWithQuery(['search' => null, 'page' => null]) }}" class="text-neutral-400 hover:text-error"><i class="ph ph-x"></i></a>
                        </span>
                    @endif
                    @if(request('status'))
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 border border-neutral-200 px-2.5 py-0.5 text-neutral-700">
                            <span class="text-neutral-400">{{ __('Status:') }}</span>
                            <span class="font-medium">{{ str(request('status'))->replace('_', ' ')->title() }}</span>
                            <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" class="text-neutral-400 hover:text-error"><i class="ph ph-x"></i></a>
                        </span>
                    @endif
                    @if(request('source'))
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 border border-neutral-200 px-2.5 py-0.5 text-neutral-700">
                            <span class="text-neutral-400">{{ __('Source:') }}</span>
                            <span class="font-medium">{{ str(request('source'))->replace('_', ' ')->title() }}</span>
                            <a href="{{ request()->fullUrlWithQuery(['source' => null, 'page' => null]) }}" class="text-neutral-400 hover:text-error"><i class="ph ph-x"></i></a>
                        </span>
                    @endif
                    @if(request('payment_state'))
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 border border-neutral-200 px-2.5 py-0.5 text-neutral-700">
                            <span class="text-neutral-400">{{ __('Payment:') }}</span>
                            <span class="font-medium">{{ str(request('payment_state'))->replace('_', ' ')->title() }}</span>
                            <a href="{{ request()->fullUrlWithQuery(['payment_state' => null, 'page' => null]) }}" class="text-neutral-400 hover:text-error"><i class="ph ph-x"></i></a>
                        </span>
                    @endif
                    <a href="{{ route('user.commerce.orders.index') }}" class="text-xs text-primary hover:underline ml-1 font-semibold">{{ __('Clear all') }}</a>
                </div>
            @endif
        </div>

        {{-- Orders Table Section --}}
        <section class="card overflow-hidden p-0">
            {{-- Table Meta Header --}}
            <div class="flex flex-col gap-2 border-b border-border-soft px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between bg-neutral-50/50">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-title">{{ __('Orders list') }}</h2>
                    <span class="rounded-full bg-neutral-200/80 px-2 py-0.5 text-[11px] font-bold text-neutral-600">{{ $orders->total() }}</span>
                </div>
                <div class="text-xs text-body">
                    @if($orders->total() > 0)
                        {{ __('Showing') }} <span class="font-semibold text-title">{{ $orders->firstItem() }}</span>–<span class="font-semibold text-title">{{ $orders->lastItem() }}</span> {{ __('of') }} <span class="font-semibold text-title">{{ $orders->total() }}</span>
                    @else
                        {{ __('0 orders found') }}
                    @endif
                </div>
            </div>

            {{-- Table Content --}}
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-border-soft bg-neutral-50/75 text-xs font-semibold uppercase tracking-wider text-neutral-600">
                            <th class="py-3 px-4">{{ __('Order') }}</th>
                            <th class="py-3 px-4">{{ __('Buyer') }}</th>
                            <th class="py-3 px-4">{{ __('Status') }}</th>
                            <th class="py-3 px-4">{{ __('Source / Payment') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Total') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Balance due') }}</th>
                            <th class="py-3 px-4">{{ __('Received') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        @forelse($orders as $order)
                            @php
                                $precision = $order->moneyPrecision();
                                $formattedTotal = number_format((float) ($order->total ?? $order->subtotal ?? 0), $precision);
                                $buyerName = $order->contact?->name ?: ($order->customer_snapshot['name'] ?? $order->contact?->phone ?? __('Guest Customer'));
                                $buyerContact = $order->customer_snapshot['phone'] ?? $order->contact?->phone ?? $order->customer_snapshot['email'] ?? $order->contact?->email;
                                $initial = mb_substr($buyerName, 0, 1);

                                $statusBadge = match($order->status) {
                                    'paid' => ['bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
                                    'completed', 'delivered' => ['bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
                                    'awaiting_payment', 'quoted', 'needs_details' => ['bg' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500'],
                                    'processing', 'packed' => ['bg' => 'bg-blue-50 text-blue-700 border-blue-200', 'dot' => 'bg-blue-500'],
                                    'shipped' => ['bg' => 'bg-purple-50 text-purple-700 border-purple-200', 'dot' => 'bg-purple-500'],
                                    'cancelled' => ['bg' => 'bg-rose-50 text-rose-700 border-rose-200', 'dot' => 'bg-rose-500'],
                                    default => ['bg' => 'bg-neutral-100 text-neutral-700 border-neutral-200', 'dot' => 'bg-neutral-400'],
                                };

                                $sourceIcon = match($order->source) {
                                    'storefront' => 'ph-globe',
                                    'pos' => 'ph-cash-register',
                                    'native_whatsapp' => 'ph-whatsapp-logo',
                                    'manual' => 'ph-pencil-simple-line',
                                    default => 'ph-shopping-cart',
                                };
                                $sourceLabel = match($order->source) {
                                    'storefront' => __('Storefront'),
                                    'pos' => __('POS'),
                                    'native_whatsapp' => __('WhatsApp'),
                                    'manual' => __('Manual'),
                                    default => str($order->source)->replace('_', ' ')->title(),
                                };
                            @endphp
                            <tr class="hover:bg-neutral-50/70 transition-colors">
                                {{-- Order Number & Tracking --}}
                                <td class="py-3 px-4">
                                    <a class="font-mono font-semibold text-primary hover:underline inline-flex items-center gap-1.5" href="{{ route('user.commerce.orders.show', $order) }}">
                                        <i class="ph ph-receipt text-neutral-400 text-base"></i>
                                        <span>{{ $order->number }}</span>
                                    </a>
                                    @if(filled($order->tracking_code ?? $order->tracking_number))
                                        <div class="mt-0.5 text-xs text-neutral-400 font-mono flex items-center gap-1">
                                            <i class="ph ph-barcode text-[11px]"></i>
                                            <span>{{ $order->tracking_code ?? $order->tracking_number }}</span>
                                        </div>
                                    @endif
                                </td>

                                {{-- Buyer Info --}}
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-neutral-100 text-xs font-bold text-neutral-600 border border-neutral-200 uppercase shrink-0">
                                            {{ $initial }}
                                        </span>
                                        <div class="min-w-0 max-w-[200px]">
                                            <div class="font-medium text-title truncate" title="{{ $buyerName }}">{{ $buyerName }}</div>
                                            @if($buyerContact)
                                                <div class="text-xs text-body truncate" title="{{ $buyerContact }}">{{ $buyerContact }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- Status Badge --}}
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusBadge['bg'] }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusBadge['dot'] }}"></span>
                                        <span>{{ str($order->status)->replace('_', ' ')->title() }}</span>
                                    </span>
                                </td>

                                {{-- Source & Payment State --}}
                                <td class="py-3 px-4">
                                    <div class="inline-flex items-center gap-1.5 text-xs font-medium text-title">
                                        <i class="ph {{ $sourceIcon }} text-sm {{ $order->source === 'native_whatsapp' ? 'text-emerald-600' : 'text-neutral-500' }}"></i>
                                        <span>{{ $sourceLabel }}</span>
                                    </div>
                                    <div class="mt-0.5 flex items-center gap-1 text-xs">
                                        <span class="text-neutral-400">{{ __('Payment:') }}</span>
                                        <span class="font-medium {{ in_array($order->payment_state, ['paid']) ? 'text-emerald-600' : (in_array($order->payment_state, ['partially_paid']) ? 'text-amber-600' : 'text-neutral-600') }}">
                                            {{ str($order->payment_state)->replace('_', ' ')->title() }}
                                        </span>
                                    </div>
                                </td>

                                {{-- Total Amount --}}
                                <td class="py-3 px-4 text-right">
                                    <span class="text-xs font-medium text-neutral-400 mr-0.5">{{ $order->currency }}</span>
                                    <span class="font-semibold text-title text-sm">{{ $formattedTotal }}</span>
                                </td>

                                {{-- Balance Due --}}
                                <td class="py-3 px-4 text-right">
                                    @if($order->source === 'pos' && $order->balanceDue() !== null)
                                        @php $bal = (float) $order->balanceDue(); @endphp
                                        @if($bal > 0)
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                                {{ $order->currency }} {{ number_format($bal, $precision) }}
                                            </span>
                                        @else
                                            <span class="text-xs text-emerald-600 font-medium inline-flex items-center gap-1">
                                                <i class="ph ph-check text-xs"></i>
                                                {{ __('Settled') }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-neutral-300">—</span>
                                    @endif
                                </td>

                                {{-- Received Date --}}
                                <td class="py-3 px-4">
                                    <div class="text-xs font-medium text-title" title="{{ $order->created_at?->format('M j, Y · g:i A') }}">
                                        {{ $order->created_at?->diffForHumans() }}
                                    </div>
                                    <div class="text-[11px] text-neutral-400">
                                        {{ $order->created_at?->format('M j, Y') }}
                                    </div>
                                </td>

                                {{-- Actions --}}
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('user.commerce.orders.show', $order) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-200 bg-neutral-0 text-neutral-600 hover:border-primary hover:text-primary transition shadow-2xs" title="{{ __('View order details') }}">
                                            <i class="ph ph-arrow-right text-base"></i>
                                            <span class="sr-only">{{ __('View order') }}</span>
                                        </a>
                                        <a href="{{ route('user.commerce.orders.packing-slip', $order) }}" target="_blank" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-200 bg-neutral-0 text-neutral-600 hover:border-primary hover:text-primary transition shadow-2xs" title="{{ __('Print packing slip') }}">
                                            <i class="ph ph-printer text-base"></i>
                                            <span class="sr-only">{{ __('Print packing slip') }}</span>
                                        </a>
                                        @if($order->source === 'pos')
                                            <a href="{{ route('user.commerce.pos.receipt', $order) }}" target="_blank" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-200 bg-neutral-0 text-neutral-600 hover:border-primary hover:text-primary transition shadow-2xs" title="{{ __('Print receipt') }}">
                                                <i class="ph ph-receipt text-base"></i>
                                                <span class="sr-only">{{ __('Print receipt') }}</span>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-12 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-neutral-100 text-neutral-400 mb-3">
                                        <i class="ph ph-shopping-bag text-2xl"></i>
                                    </div>
                                    <h3 class="text-base font-semibold text-title">{{ __('No orders found') }}</h3>
                                    <p class="mt-1 text-sm text-body max-w-sm mx-auto">
                                        @if(request()->anyFilled(['search', 'status', 'source', 'payment_state', 'contact_id', 'from', 'to']))
                                            {{ __('No orders matched your current filters. Try adjusting your search query or removing some filters.') }}
                                        @else
                                            {{ __('No orders have arrived yet. When customer requests or POS sales come in, they will appear here.') }}
                                        @endif
                                    </p>
                                    @if(request()->anyFilled(['search', 'status', 'source', 'payment_state', 'contact_id', 'from', 'to']))
                                        <div class="mt-4">
                                            <a href="{{ route('user.commerce.orders.index') }}" class="btn btn-sm btn-outline inline-flex items-center gap-1.5">
                                                <i class="ph ph-arrow-counter-clockwise"></i>
                                                <span>{{ __('Reset all filters') }}</span>
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
            @if($orders->hasPages())
                <div class="border-t border-border-soft px-5 py-4">
                    {{ $orders->links() }}
                </div>
            @endif
        </section>
    </div>
</x-layouts.user>
