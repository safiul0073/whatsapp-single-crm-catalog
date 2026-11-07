<x-layouts.document :title="$order->number.' · '.__('Packing slip')" :back="route('user.commerce.orders.show', $order)" format="a4">
    @php
        $boxes = $order->boxes->sortBy(fn ($box) => preg_match('/(?:BOX-|#)(\d+)/i', $box->label, $matches) ? (int) $matches[1] : (int) $box->id)->values();
        $totalPieces = $boxes->flatMap->contents->sum('quantity');
        $packedCount = $boxes->whereNotNull('packed_at')->count();
        $sizeOrder = array_flip(\App\Modules\Commerce\Services\OrderDocumentPresenter::SIZE_ORDER);
        $customerName = $customer['name'] ?? __('Customer');
        $customerPhone = $customer['phone'];
        $addressLine = collect($shipTo)->filter()->implode(', ');
        $sizeRank = fn ($content) => $sizeOrder[strtoupper($content->item->attributes['size'] ?? '')] ?? 99;
        $packTiles = $boxes->flatMap(fn ($box) => $box->contents
            ->groupBy(fn ($content) => $content->item->product_name.'|'.($content->item->attributes['color'] ?? ''))
            ->flatMap(function ($contents) use ($box, $sizeRank) {
                $contents = $contents->sortBy($sizeRank)->values();
                $total = (int) $contents->sum('quantity');
                $perPack = $box->group?->mode === 'wholesale' ? (int) $box->group->pieces_per_box : 0;
                $packCount = $perPack > 0 && $total > $perPack && $total % $perPack === 0 ? intdiv($total, $perPack) : 1;
                $packCount = $contents->every(fn ($content) => $content->quantity % $packCount === 0) ? $packCount : 1;
                $quantities = $contents->map(fn ($content) => intdiv((int) $content->quantity, $packCount));

                return collect(range(1, $packCount))->map(fn () => [
                    'box' => $box,
                    'item' => $contents->first()->item,
                    'color' => (string) ($contents->first()->item->attributes['color'] ?? ''),
                    'sizes' => $contents->map(fn ($content) => $content->item->attributes['size'] ?? '—'),
                    'quantities' => $quantities,
                    'pieces' => (int) $quantities->sum(),
                ]);
            }))
            ->values()
            ->map(fn (array $tile, int $index): array => $tile + ['number' => $index + 1]);
        $packNumbers = function ($numbers): string {
            $ranges = [];
            foreach ($numbers->sort()->values() as $number) {
                $last = array_key_last($ranges);
                if ($last !== null && $ranges[$last][1] + 1 === $number) {
                    $ranges[$last][1] = $number;
                } else {
                    $ranges[] = [$number, $number];
                }
            }

            return collect($ranges)->map(fn ($range) => $range[0] === $range[1] ? '#'.$range[0] : '#'.$range[0].'–'.$range[1])->implode(', ');
        };
    @endphp
        <header class="flex items-start justify-between gap-4 border-b border-border-default pb-2">
            <div>
                <p class="text-xs text-body">{{ $brand['name'] }}</p>
                <h1 class="text-lg font-semibold leading-tight">{{ __('Packing slip') }}</h1>
                <p class="text-xs text-body">{{ $order->created_at->format('M j, Y · g:i A') }} · {{ str($order->source)->replace('_', ' ')->title() }}</p>
            </div>
            <div class="text-right text-xs">
                <p class="text-base font-semibold">{{ $order->number }}</p>
                <p class="text-body">{{ str($order->status)->replace('_', ' ')->title() }} · {{ __('Payment') }}: {{ str($order->payment_state)->replace('_', ' ')->title() }}</p>
            </div>
        </header>

        <section class="grid grid-cols-3 gap-3 text-xs">
            <div>
                <p class="document-label">{{ __('Customer') }}</p>
                <p class="font-medium">{{ $customerName }}</p>
                @if($customerPhone)<p class="text-body">{{ $customerPhone }}</p>@endif
            </div>
            <div>
                <p class="document-label">{{ __('Ship to') }}</p>
                <p class="text-body">{{ $addressLine !== '' ? $addressLine : __('Counter pickup') }}</p>
            </div>
            <div class="text-right">
                <p class="document-label">{{ __('Packing') }}</p>
                <p class="font-medium">{{ $packTiles->count() }} {{ __('packs') }} · {{ $totalPieces }} {{ __('pcs') }}</p>
                <p class="text-body">{{ $packedCount }} / {{ $boxes->count() }} {{ __('packed') }}</p>
            </div>
        </section>

        @if($packTiles->isEmpty())
            <p class="rounded-md bg-section p-4 text-center text-sm text-body">{{ __('No boxes have been created for this order yet.') }}</p>
        @else
            @foreach($packTiles->groupBy(fn ($tile) => $tile['item']->product_name) as $productName => $productTiles)
                <section class="space-y-1 break-inside-avoid-page">
                    <h2 class="flex items-baseline justify-between gap-3 border-b border-border-default pb-0.5 text-sm font-semibold">
                        <span>{{ $productName }}</span>
                        <span class="text-xs font-normal text-body">{{ $productTiles->count() }} {{ __('packs') }} · {{ $productTiles->sum('pieces') }} {{ __('pcs') }}</span>
                    </h2>
                    <div class="packing-cards">
                        @foreach($productTiles->groupBy(fn ($tile) => $tile['color'].'|'.$tile['quantities']->implode(':')) as $cardTiles)
                            @php
                                $first = $cardTiles->first();
                                $image = $first['item']->productImages()[0] ?? null;
                                $packCount = $cardTiles->count();
                            @endphp
                            <article class="packing-card" data-packing-card="{{ $first['color'] }}">
                                <p class="text-[11px] font-semibold leading-tight">{{ filled($first['color']) ? $first['color'] : __('No color') }}</p>
                                <div class="packing-card-images">
                                    @foreach($cardTiles as $tile)
                                        <div class="packing-card-thumb" data-packing-tile title="#{{ $tile['number'] }}">
                                            @if($image)
                                                <img src="{{ $image['url'] }}" alt="{{ $image['label'] }}">
                                            @else
                                                <i class="ph ph-image grid size-full place-items-center text-lg text-body" aria-hidden="true"></i>
                                            @endif
                                            <span class="packing-state {{ $tile['box']->packed_at ? 'text-primary' : 'text-body' }}">
                                                <i class="ph {{ $tile['box']->packed_at ? 'ph-check-square' : 'ph-square' }}" aria-hidden="true"></i>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="packing-card-details" data-packing-details>
                                    <p><strong class="font-semibold text-title">{{ $packCount }} {{ $packCount === 1 ? __('pack') : __('packs') }} · {{ $cardTiles->sum('pieces') }} {{ __('pcs') }}</strong> · {{ $packNumbers($cardTiles->pluck('number')) }}</p>
                                    <p>{{ $first['sizes']->first() }}–{{ $first['sizes']->last() }} · {{ $first['quantities']->implode(':') }} {{ __('per pack') }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif

        <footer class="grid grid-cols-2 gap-6 pt-3 text-xs text-body break-inside-avoid">
            <p class="border-t border-border-strong pt-1">{{ __('Packed by') }}</p>
            <p class="border-t border-border-strong pt-1">{{ __('Checked by') }}</p>
        </footer>
</x-layouts.document>
