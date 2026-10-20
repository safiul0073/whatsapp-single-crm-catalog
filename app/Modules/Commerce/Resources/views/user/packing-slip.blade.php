<x-layouts.user :title="__('Packing Slip · ') . $order->number">
    @php
        $address = $order->shipping_address ?? [];
        $contactPerson = $order->contact;
        $customerName = $contactPerson?->name ?? ($order->customer_snapshot['name'] ?? ($address['name'] ?? __('Customer')));
        $customerPhone = $contactPerson?->phone ?? ($order->customer_snapshot['phone'] ?? ($address['phone'] ?? null));
        $customerEmail = $contactPerson?->email ?? ($order->customer_snapshot['email'] ?? ($address['email'] ?? null));

        // Format destination address
        $addressLines = collect([
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            collect([$address['city'] ?? null, $address['state'] ?? null, $address['postal_code'] ?? null])->filter()->implode(', '),
            $address['country'] ?? null,
        ])->filter()->values();

        // Natural sort boxes so BOX-1, BOX-2, ..., BOX-10 stay in proper numeric order
        $sortedBoxes = $order->boxes->sortBy(function ($box) {
            if (preg_match('/(?:BOX-|#)(\d+)/i', $box->label, $matches)) {
                return (int) $matches[1];
            }
            return (int) $box->id;
        })->values();

        $totalBoxes = $sortedBoxes->count();
        $totalPieces = $sortedBoxes->flatMap->contents->sum('quantity');
        $packedBoxesCount = $sortedBoxes->whereNotNull('packed_at')->count();
        $awaitingBoxesCount = $totalBoxes - $packedBoxesCount;

        $sizeOrder = array_flip(['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', 'XXXL', '4XL', '5XL']);
    @endphp

    @push('styles')
    <style>
        @media print {
            aside,
            header.topbar,
            .app-shell > header,
            nav,
            .user-sidebar,
            .print\:hidden {
                display: none !important;
            }
            .app-shell {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            body {
                background-color: #ffffff !important;
                color: #0f172a !important;
                font-size: 11pt;
            }
            main {
                padding: 0 !important;
                margin: 0 !important;
            }
            .print-card {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                background: #ffffff !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            .print-contrast {
                background-color: #f8fafc !important;
                border: 1px solid #e2e8f0 !important;
            }
        }
    </style>
    @endpush

    <div class="space-y-6 max-w-5xl mx-auto">
        {{-- Navigation & Actions Bar (Hidden on print) --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 print:hidden">
            <a href="{{ route('user.commerce.orders.show', $order) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 transition">
                <i class="ph ph-arrow-left text-sm"></i>
                <span>{{ __('Back to Order') }} {{ $order->number }}</span>
            </a>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    id="printPackingSlip"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md shadow-indigo-600/20 hover:bg-indigo-500 transition">
                    <i class="ph ph-printer text-base"></i>
                    <span>{{ __('Print Packing Slip') }}</span>
                </button>
            </div>
        </div>

        {{-- Order Header & Warehouse Summary Card (Borderless, Soft Shadow) --}}
        <section class="rounded-2xl bg-white dark:bg-slate-900 p-6 sm:p-8 shadow-sm space-y-6 print-card">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-start gap-3.5">
                    <span class="grid size-12 place-items-center rounded-2xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20 shrink-0">
                        <i class="ph ph-receipt text-2xl"></i>
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                                {{ __('Packing Slip') }} · <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ $order->number }}</span>
                            </h1>
                            <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {{ str($order->status)->replace('_', ' ')->title() }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            {{ __('Placed') }} {{ $order->created_at?->format('M j, Y · g:i A') }}
                            @if($order->source)
                                · {{ __('Source') }}: <span class="font-medium text-slate-700 dark:text-slate-300">{{ str($order->source)->replace('_', ' ')->title() }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 self-start">
                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                        <i class="ph ph-credit-card text-slate-400"></i>
                        <span>{{ __('Payment') }}: <strong class="text-slate-900 dark:text-white">{{ str($order->payment_state)->replace('_', ' ')->title() }}</strong></span>
                    </span>
                </div>
            </div>

            {{-- 3-Column Logistics Info Grid --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 text-xs">
                {{-- Recipient & Contact --}}
                <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-4 space-y-2 print-contrast">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="ph ph-user text-indigo-500 text-sm"></i>
                        {{ __('Customer & Contact') }}
                    </span>
                    <div class="space-y-1">
                        <p class="font-bold text-sm text-slate-900 dark:text-white">{{ $customerName }}</p>
                        @if($customerPhone)
                            <p class="text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
                                <i class="ph ph-phone text-slate-400"></i>
                                <span>{{ $customerPhone }}</span>
                            </p>
                        @endif
                        @if($customerEmail)
                            <p class="text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
                                <i class="ph ph-envelope-simple text-slate-400"></i>
                                <span class="truncate">{{ $customerEmail }}</span>
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Shipping Destination --}}
                <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-4 space-y-2 print-contrast">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="ph ph-map-pin text-indigo-500 text-sm"></i>
                        {{ __('Shipping Destination') }}
                    </span>
                    <div class="space-y-0.5 text-slate-700 dark:text-slate-300 leading-relaxed">
                        @if($addressLines->isNotEmpty())
                            @foreach($addressLines as $line)
                                <p>{{ $line }}</p>
                            @endforeach
                        @else
                            <p class="text-slate-400 italic">{{ __('No shipping address recorded') }}</p>
                        @endif
                    </div>
                </div>

                {{-- Packing Statistics Overview --}}
                <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-4 space-y-2 sm:col-span-2 lg:col-span-1 print-contrast">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="ph ph-package text-indigo-500 text-sm"></i>
                        {{ __('Packing Overview') }}
                    </span>
                    <div class="grid grid-cols-2 gap-2 pt-0.5">
                        <div class="rounded-lg bg-white dark:bg-slate-900/80 p-2 text-center shadow-2xs">
                            <span class="text-[10px] text-slate-400 font-medium block">{{ __('Total Boxes') }}</span>
                            <span class="text-base font-bold text-slate-900 dark:text-white tabular-nums">{{ $totalBoxes }}</span>
                        </div>
                        <div class="rounded-lg bg-white dark:bg-slate-900/80 p-2 text-center shadow-2xs">
                            <span class="text-[10px] text-slate-400 font-medium block">{{ __('Total Pieces') }}</span>
                            <span class="text-base font-bold text-indigo-600 dark:text-indigo-400 tabular-nums">{{ $totalPieces }}</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 pt-1">
                        <span>{{ __('Packed') }}: <strong class="text-emerald-600 dark:text-emerald-400">{{ $packedBoxesCount }}</strong></span>
                        <span>•</span>
                        <span>{{ __('Awaiting') }}: <strong class="text-amber-600 dark:text-amber-400">{{ $awaitingBoxesCount }}</strong></span>
                    </div>
                </div>
            </div>
        </section>

        {{-- Organized Boxes Section --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between px-1">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                    <i class="ph ph-stack text-indigo-600 dark:text-indigo-400 text-base"></i>
                    <span>{{ __('Fulfillment Packing Boxes') }}</span>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        {{ $totalBoxes }}
                    </span>
                </h2>
            </div>

            @forelse($sortedBoxes as $box)
                @php
                    $boxItems = $box->contents;
                    $boxTotalPieces = $boxItems->sum('quantity');
                    $customPackSnapshot = $box->group?->pack_snapshot;
                    $customPackName = $customPackSnapshot['name'] ?? null;
                    $isWholesale = $box->kind === 'wholesale';
                    $isRetail = $box->kind === 'retail';

                    // Group contents inside this box by product and color to prevent repetitive duplication
                    $groupedContents = $boxItems->groupBy(function ($content) {
                        $item = $content->item;
                        $prod = $item->variant?->product_id ?? $item->product_name;
                        $color = $item->attributes['color'] ?? '';
                        return $prod . '||' . $color;
                    });
                @endphp

                <article class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-sm space-y-4 transition page-break-avoid print-card">
                    {{-- Box Header --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/70 dark:text-indigo-400 font-bold shrink-0">
                                <i class="ph ph-package text-xl"></i>
                            </span>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                        {{ __('Box') }} #{{ $loop->iteration }} {{ __('of') }} {{ $totalBoxes }}
                                    </h3>
                                    <span class="font-mono text-xs font-medium text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md">
                                        {{ $box->label }}
                                    </span>
                                    @if($customPackName)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-500/10 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:text-indigo-300">
                                            <i class="ph ph-package"></i> {{ $customPackName }}
                                        </span>
                                    @elseif($isWholesale)
                                        <span class="inline-flex items-center rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:text-blue-300">
                                            {{ __('Wholesale Pack') }}
                                        </span>
                                    @elseif($isRetail)
                                        <span class="inline-flex items-center rounded-full bg-purple-500/10 px-2.5 py-0.5 text-xs font-semibold text-purple-700 dark:text-purple-300">
                                            {{ __('Retail Box') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 px-3 py-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                <i class="ph ph-stack text-indigo-500"></i>
                                <span>{{ $boxTotalPieces }} {{ __('pieces') }}</span>
                            </span>

                            @if($box->packed_at)
                                <span class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500/10 px-3 py-1.5 text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                    <i class="ph ph-check-circle text-base"></i>
                                    <span>{{ __('Packed') }}</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500/10 px-3 py-1.5 text-xs font-bold text-amber-700 dark:text-amber-400">
                                    <i class="ph ph-clock text-base"></i>
                                    <span>{{ __('Awaiting packing') }}</span>
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Box Contents Organized by Product & Color --}}
                    <div class="space-y-3.5">
                        @foreach($groupedContents as $groupKey => $contents)
                            @php
                                $firstItem = $contents->first()->item;
                                $productName = $firstItem->product_name;
                                $colorName = $firstItem->attributes['color'] ?? null;
                                $colorCode = $firstItem->variant?->color?->color_code;
                                $categoryName = $firstItem->variant?->product?->category?->name;
                                $imageUrl = $firstItem->productImages()[0]['url'] ?? $firstItem->variant?->product?->primaryMedia?->url;
                                $groupPieces = $contents->sum('quantity');
                                $sortedVariants = $contents->sortBy(fn ($c) => $sizeOrder[strtoupper($c->item->attributes['size'] ?? '')] ?? 99);
                            @endphp

                            <div class="rounded-xl bg-slate-50 dark:bg-slate-800/40 p-4 space-y-3 print-contrast">
                                {{-- Product & Color Bar --}}
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-2.5 border-b border-slate-200/60 dark:border-slate-700/60">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="{{ $productName }}"
                                                 width="40" height="40"
                                                 style="width: 40px; height: 40px; min-width: 40px; max-width: 40px; object-fit: cover;"
                                                 class="rounded-lg object-cover shrink-0 bg-slate-200 dark:bg-slate-700 shadow-2xs">
                                        @else
                                            <div style="width: 40px; height: 40px; min-width: 40px; max-width: 40px;"
                                                 class="rounded-lg bg-slate-200/80 dark:bg-slate-700/60 flex items-center justify-center text-slate-400 shrink-0">
                                                <i class="ph ph-t-shirt text-lg"></i>
                                            </div>
                                        @endif

                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $productName }}</h4>
                                                @if($categoryName)
                                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold bg-slate-200/70 dark:bg-slate-700 text-slate-600 dark:text-slate-300 shrink-0">{{ $categoryName }}</span>
                                                @endif
                                            </div>
                                            @if($colorName)
                                                <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                    @if($colorCode)
                                                        <span class="size-2.5 rounded-full border border-slate-300 dark:border-slate-600 shrink-0 shadow-2xs" style="background-color: {{ $colorCode }};"></span>
                                                    @endif
                                                    <span>{{ __('Color') }}: <strong class="text-slate-700 dark:text-slate-300">{{ $colorName }}</strong></span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <span class="inline-flex items-center self-start sm:self-center rounded-lg bg-white dark:bg-slate-800 px-2.5 py-1 text-xs font-bold text-slate-700 dark:text-slate-300 shadow-2xs shrink-0">
                                        {{ $groupPieces }} {{ __('pcs') }}
                                    </span>
                                </div>

                                {{-- Variants Checklist Table --}}
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead>
                                            <tr class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                                                <th scope="col" class="py-1.5 pl-1 pr-3 w-12 text-center">{{ __('Check') }}</th>
                                                <th scope="col" class="py-1.5 px-3">{{ __('SKU') }}</th>
                                                <th scope="col" class="py-1.5 px-3">{{ __('Size / Variant') }}</th>
                                                <th scope="col" class="py-1.5 px-3 text-right">{{ __('Quantity') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200/50 dark:divide-slate-700/50">
                                            @foreach($sortedVariants as $variantContent)
                                                @php
                                                    $varItem = $variantContent->item;
                                                    $size = $varItem->attributes['size'] ?? ($varItem->attributes['option'] ?? '—');
                                                @endphp
                                                <tr class="hover:bg-white/70 dark:hover:bg-slate-800/40 transition">
                                                    {{-- Warehouse Checklist Box --}}
                                                    <td class="py-2 pl-1 pr-3 text-center">
                                                        <span class="inline-block size-4 rounded border-2 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900"></span>
                                                    </td>
                                                    {{-- SKU --}}
                                                    <td class="py-2 px-3 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                                                        {{ $varItem->sku ?: '—' }}
                                                    </td>
                                                    {{-- Size badge --}}
                                                    <td class="py-2 px-3">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-white dark:bg-slate-900 text-xs font-bold text-slate-800 dark:text-slate-200 shadow-2xs">
                                                            {{ $size }}
                                                        </span>
                                                    </td>
                                                    {{-- Quantity --}}
                                                    <td class="py-2 px-3 text-right font-bold text-slate-900 dark:text-white tabular-nums">
                                                        {{ $variantContent->quantity }} {{ __('pcs') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-10 text-center shadow-xs space-y-3 print-card">
                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400">
                        <i class="ph ph-package text-2xl"></i>
                    </span>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('No Packing Boxes Configured') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                        {{ __('No packing boxes have been allocated for this order yet.') }}
                    </p>
                    <a href="{{ route('user.commerce.orders.show', $order) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-md shadow-indigo-600/20 hover:bg-indigo-500 transition print:hidden">
                        {{ __('View Order Details') }}
                    </a>
                </div>
            @endforelse
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('printPackingSlip')?.addEventListener('click', function () {
            'use strict';
            window.print();
        });
    </script>
    @endpush
</x-layouts.user>
