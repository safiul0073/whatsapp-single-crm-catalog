                @if($order->boxes->isNotEmpty())
                    <section id="packing" class="section-card space-y-6 scroll-mt-24">
                        <div class="space-y-3" data-packing-progress data-total="{{ $order->boxes->count() }}" data-status="{{ $order->status }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h2 class="heading-5 text-title">{{ __('Packing checklist') }}</h2>
                                <span class="text-sm text-body">{{ $order->boxes->count() }} {{ __('packs') }} · {{ $packedPieces }} {{ __('pieces') }}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="h-2 min-w-40 flex-1 overflow-hidden rounded-full bg-section" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $order->boxes->count() }}" aria-valuenow="{{ $packedBoxCount }}" data-packing-bar>
                                    <div class="h-full rounded-full bg-primary transition-all" data-packing-bar-fill data-width="{{ $order->boxes->count() ? round($packedBoxCount / $order->boxes->count() * 100) : 0 }}"></div>
                                </div>
                                <span class="text-sm font-medium text-title"><span data-packing-count>{{ $packedBoxCount }}</span> / {{ $order->boxes->count() }} {{ __('packs packed') }}</span>
                                @if($canPack && $packedBoxCount < $order->boxes->count())
                                    <form method="POST" action="{{ route('user.commerce.orders.boxes.packed-all', $order) }}" data-packing-all>
                                        @csrf @method('PUT')
                                        <button type="submit" class="btn btn-outline btn-sm">{{ __('Mark all packed') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @foreach($packProductGroups as $productBoxes)
                            @php $packProducts = $productBoxes->flatMap->contents->map->item->unique($productKey); @endphp
                            <div class="space-y-3" data-pack-product-group>
                                @php
                                    $customPackSnapshot = $productBoxes->first()?->group?->pack_snapshot;
                                    $customPackName = $customPackSnapshot['name'] ?? null;
                                @endphp
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border-soft pb-2">
                                    <h3 class="text-sm font-semibold leading-snug text-title flex items-center gap-1.5">
                                        @if($customPackName)
                                            <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20 dark:bg-indigo-950 dark:text-indigo-300">{{ $customPackName }}</span>
                                        @endif
                                        <span>{{ $packProducts->count() === 1 ? $packProducts->first()->product_name : __('Mixed products') }}</span>
                                    </h3>
                                    @if($packProductGroups->count() > 1)
                                        <span class="text-xs text-body">{{ $productBoxes->count() }} {{ __('packs') }} · {{ $productBoxes->flatMap->contents->sum('quantity') }} {{ __('pieces') }}</span>
                                    @endif
                                </div>
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                                    @foreach($productBoxes as $box)
                                        <article class="min-w-0 space-y-2 rounded-md border border-border-soft bg-bg-elevated p-2" data-pack-card data-pack-id="{{ $box->id }}">
                                            <div class="space-y-2">
                                                @foreach($box->contents->groupBy(fn ($content) => $productKey($content->item).'|'.($content->item->attributes['color'] ?? ''))->map(fn ($contents) => $contents->sortBy(fn ($content) => $sizeOrder[strtoupper($content->item->attributes['size'] ?? '')] ?? 99)->values()) as $productColorContents)
                                                    @php
                                                        $packItem = $productColorContents->first()->item;
                                                        $packSizes = $productColorContents->map(fn ($content) => $content->item->attributes['size'] ?? '—');
                                                    @endphp
                                                    <div class="space-y-1">
                                                        @include('commerce::user.partials.order-product-image', ['item' => $packItem, 'sizeClass' => 'w-full', 'padClass' => 'p-0'])
                                                        @if($packProducts->count() > 1)
                                                            <p class="truncate text-xs font-semibold leading-snug text-title">{{ $packItem->product_name }}</p>
                                                        @endif
                                                        <p class="text-xs leading-tight text-body"><strong class="font-semibold text-title">{{ $packItem->attributes['color'] ?? '—' }}</strong> · {{ $productColorContents->sum('quantity') }} {{ __('pcs') }}</p>
                                                        <p class="text-[11px] leading-tight text-body" title="{{ $packSizes->implode(', ') }}">{{ $packSizes->first() }}–{{ $packSizes->last() }} · {{ $productColorContents->pluck('quantity')->implode(':') }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="border-t border-border-soft pt-2">
                                                @if($canPack)
                                                    <form method="POST" action="{{ route('user.commerce.orders.boxes.packed', [$order, $box]) }}" data-packing-toggle-form>
                                                        @csrf @method('PUT')
                                                        <label class="flex cursor-pointer flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-title">
                                                            <input type="checkbox" class="app-checkbox" name="packed" value="1" @checked($box->packed_at) data-packing-toggle>
                                                            <span>{{ __('Packed') }}</span>
                                                            <span class="w-full pl-6 text-[10px] text-body empty:hidden" data-packed-at>@if($box->packed_at){{ $box->packed_at->format('M j, g:i A') }}@endif</span>
                                                        </label>
                                                    </form>
                                                @else
                                                    <p class="flex items-center gap-1.5 text-xs {{ $box->packed_at ? 'text-primary' : 'text-body' }}" @if($box->packed_at) title="{{ $box->packed_at->format('M j, g:i A') }}" @endif>
                                                        <i class="ph {{ $box->packed_at ? 'ph-check-square' : 'ph-square' }} text-sm" aria-hidden="true"></i>
                                                        {{ $box->packed_at ? __('Packed') : __('Not packed yet') }}
                                                    </p>
                                                @endif
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </section>
@endif
