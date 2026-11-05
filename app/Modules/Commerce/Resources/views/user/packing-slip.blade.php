<x-layouts.document :title="$order->number.' · '.__('Packing slip')" :back="route('user.commerce.orders.show', $order)" format="a4">
    @php
        $boxes = $order->boxes->sortBy(fn ($box) => preg_match('/(?:BOX-|#)(\d+)/i', $box->label, $matches) ? (int) $matches[1] : (int) $box->id)->values();
        $totalPieces = $boxes->flatMap->contents->sum('quantity');
        $packedCount = $boxes->whereNotNull('packed_at')->count();
        $sizeOrder = array_flip(\App\Modules\Commerce\Services\OrderDocumentPresenter::SIZE_ORDER);
        $customerName = $customer['name'] ?? __('Customer');
        $customerPhone = $customer['phone'];
        $addressLine = collect($shipTo)->filter()->implode(', ');
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
                <p class="font-medium">{{ $boxes->count() }} {{ __('boxes') }} · {{ $totalPieces }} {{ __('pcs') }}</p>
                <p class="text-body">{{ $packedCount }} / {{ $boxes->count() }} {{ __('packed') }}</p>
            </div>
        </section>

        @php
            $packs = $boxes->flatMap(fn ($box) => $box->contents
                ->groupBy(fn ($content) => $content->item->product_name.'|'.($content->item->attributes['color'] ?? ''))
                ->map(fn ($contents) => ['box' => $box, 'index' => $boxes->search($box) + 1, 'contents' => $contents->sortBy(fn ($content) => $sizeOrder[strtoupper($content->item->attributes['size'] ?? '')] ?? 99)->values()]))
                ->groupBy(fn ($pack) => $pack['contents']->first()->item->product_name);
        @endphp
        @if($packs->isEmpty())
            <p class="rounded-md bg-section p-4 text-center text-sm text-body">{{ __('No boxes have been created for this order yet.') }}</p>
        @else
            @foreach($packs as $productName => $productPacks)
                <section class="space-y-1.5 break-inside-avoid-page">
                    <h2 class="flex items-baseline justify-between gap-3 border-b border-border-default pb-1 text-sm font-semibold">
                        <span>{{ $productName }}</span>
                        <span class="text-xs font-normal text-body">{{ $productPacks->count() }} {{ __('packs') }} · {{ $productPacks->sum(fn ($pack) => $pack['contents']->sum('quantity')) }} {{ __('pcs') }}</span>
                    </h2>
                    <div class="packing-grid">
                        @foreach($productPacks as $pack)
                            @php
                                $contents = $pack['contents'];
                                $item = $contents->first()->item;
                                $image = $item->productImages()[0] ?? null;
                                $color = $item->attributes['color'] ?? null;
                                $sizes = $contents->map(fn ($content) => $content->item->attributes['size'] ?? '—');
                            @endphp
                            <article class="packing-tile">
                                <div class="packing-thumb">
                                    @if($image)
                                        <img src="{{ $image['url'] }}" alt="{{ $image['label'] }}" class="block h-auto w-full">
                                    @else
                                        <i class="ph ph-image grid aspect-square place-items-center text-2xl text-body" aria-hidden="true"></i>
                                    @endif
                                    <span class="packing-state {{ $pack['box']->packed_at ? 'text-primary' : 'text-body' }}">
                                        <i class="ph {{ $pack['box']->packed_at ? 'ph-check-square' : 'ph-square' }}" aria-hidden="true"></i>
                                    </span>
                                </div>
                                <p class="packing-meta">
                                    <strong class="font-semibold">{{ filled($color) ? $color : __('No color') }}</strong>
                                    · {{ $pack['index'] }}/{{ $boxes->count() }} · {{ $contents->sum('quantity') }} {{ __('pcs') }}
                                </p>
                                <p class="packing-meta">{{ $sizes->first() }}–{{ $sizes->last() }} · {{ $contents->pluck('quantity')->implode(':') }}</p>
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
