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
                    <h2 class="flex items-baseline justify-between gap-3 border-b border-border-default pb-1 text-sm font-semibold">
                        <span>{{ $productName }}</span>
                        <span class="text-xs font-normal text-body">{{ $productTiles->count() }} {{ __('packs') }} · {{ $productTiles->sum('pieces') }} {{ __('pcs') }}</span>
                    </h2>
                    @foreach($productTiles->groupBy('color') as $colorName => $colorTiles)
                        <div class="packing-color-row" data-packing-color="{{ $colorName }}">
                            <div class="packing-color-label">
                                <p class="text-xs font-semibold">{{ filled($colorName) ? $colorName : __('No color') }}</p>
                                <p class="packing-meta">{{ $colorTiles->count() }} {{ $colorTiles->count() === 1 ? __('pack') : __('packs') }} · {{ $colorTiles->sum('pieces') }} {{ __('pcs') }}</p>
                            </div>
                            <div class="packing-strip">
                                @foreach($colorTiles as $tile)
                                    @php($image = $tile['item']->productImages()[0] ?? null)
                                    <article class="packing-tile" data-packing-tile>
                                        <div class="packing-thumb">
                                            @if($image)
                                                <img src="{{ $image['url'] }}" alt="{{ $image['label'] }}" class="block h-auto w-full">
                                            @else
                                                <i class="ph ph-image grid aspect-square place-items-center text-2xl text-body" aria-hidden="true"></i>
                                            @endif
                                            <span class="packing-state {{ $tile['box']->packed_at ? 'text-primary' : 'text-body' }}">
                                                <i class="ph {{ $tile['box']->packed_at ? 'ph-check-square' : 'ph-square' }}" aria-hidden="true"></i>
                                            </span>
                                        </div>
                                        <p class="packing-meta font-semibold">#{{ $tile['number'] }}/{{ $packTiles->count() }}</p>
                                        <p class="packing-meta">{{ $tile['pieces'] }} {{ __('pcs') }}</p>
                                        <p class="packing-meta">{{ $tile['sizes']->first() }}–{{ $tile['sizes']->last() }} · {{ $tile['quantities']->implode(':') }}</p>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </section>
            @endforeach
        @endif

        <footer class="grid grid-cols-2 gap-6 pt-3 text-xs text-body break-inside-avoid">
            <p class="border-t border-border-strong pt-1">{{ __('Packed by') }}</p>
            <p class="border-t border-border-strong pt-1">{{ __('Checked by') }}</p>
        </footer>
</x-layouts.document>
