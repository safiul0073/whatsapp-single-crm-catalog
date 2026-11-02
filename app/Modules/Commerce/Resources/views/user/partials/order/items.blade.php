                @if($unpackedItems->isNotEmpty())
                    <section class="section-card space-y-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="heading-5 text-title">{{ __('Ordered items') }}</h2>
                            <span class="text-sm text-body">{{ $unpackedItems->sum(fn ($item) => $item->quantity - ($packedQuantities[$item->id] ?? 0)) }} {{ __('pieces') }}</span>
                        </div>
                        @foreach($individualProductGroups as $productItems)
                            <div class="space-y-3" data-individual-product-group>
                                <h3 class="border-b border-border-soft pb-3 text-sm font-semibold leading-snug text-title">{{ $productItems->first()->product_name }}</h3>
                                <div class="grid gap-3 md:grid-cols-2">
                                    @foreach($productItems as $item)
                                        @php $remainingQuantity = $item->quantity - ($packedQuantities[$item->id] ?? 0); @endphp
                                        <article class="min-w-0 space-y-4 rounded-md border border-border-soft bg-bg-elevated p-4" data-order-item="{{ $item->id }}">
                                            <div class="flex items-center gap-3">
                                                @include('commerce::user.partials.order-product-image', ['item' => $item])
                                                <div class="min-w-0 flex-1 space-y-1.5">
                                                    <h4 class="text-sm font-semibold text-title">{{ __('Color') }}: {{ $item->attributes['color'] ?? '—' }}</h4>
                                                    <p class="text-sm text-body">{{ __('Size') }}: <span class="font-medium text-title">{{ $item->attributes['size'] ?? '—' }}</span></p>
                                                    <p class="break-all text-xs text-body">{{ __('SKU') }}: {{ $item->sku ?: '—' }}</p>
                                                </div>
                                            </div>
                                            <dl class="grid grid-cols-3 gap-3 border-t border-border-soft pt-3 text-xs">
                                                <div><dt class="text-body">{{ __('Quantity') }}</dt><dd class="mt-1 text-sm font-semibold tabular-nums text-title" data-item-quantity>{{ $remainingQuantity }}</dd></div>
                                                <div><dt class="text-body">{{ __('Unit price') }}</dt><dd class="mt-1 break-words font-medium tabular-nums text-title">{{ $money($item->unit_price) }}</dd></div>
                                                <div><dt class="text-body">{{ __('Total') }}</dt><dd class="mt-1 break-words font-semibold tabular-nums text-title" data-item-total>{{ $money($remainingQuantity === $item->quantity ? $item->line_total : $remainingQuantity * (float) $item->unit_price) }}</dd></div>
                                            </dl>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endif
