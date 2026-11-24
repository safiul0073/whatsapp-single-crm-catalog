<x-layouts.document :title="$order->number.' · '.__('Packing slip')" :back="route('user.commerce.orders.show', $order)" format="a4">
    @php
        $presenter = app(\App\Modules\Commerce\Services\OrderDocumentPresenter::class);
        $boxes = $presenter->boxes($order);
        $totalPieces = $boxes->flatMap->contents->sum('quantity');
        $packedCount = $boxes->whereNotNull('packed_at')->count();
        $customerName = $customer['name'] ?? __('Customer');
        $addressLine = collect($shipTo)->filter()->implode(', ');
        $packTiles = $presenter->packTiles($order);
        $packNumbers = fn ($numbers): string => $presenter->packNumbers($numbers);
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
                @if(filled($customer['phone'] ?? null))<p class="text-body">{{ $customer['phone'] }}</p>@endif
                @if(filled($customer['email'] ?? null))<p class="text-body">{{ $customer['email'] }}</p>@endif
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
                            <article class="packing-card packing-card-span-{{ min($packCount, 4) }}" data-packing-card="{{ $first['color'] }}">
                                <p class="text-[11px] font-semibold leading-tight">{{ filled($first['color']) ? $first['color'] : __('No color') }}</p>
                                <div class="packing-card-images packing-card-images-{{ min($packCount, 4) }}">
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
