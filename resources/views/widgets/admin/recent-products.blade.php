<div class="section-card">
    <h2 class="heading-5 text-neutral-950 mb-4">{{ __('Recent Products') }}</h2>

    @if($products->isNotEmpty())
        <div class="space-y-2">
            @foreach($products as $product)
            <div class="flex items-center gap-3 rounded-md border border-neutral-100 bg-neutral-0 p-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <i class="ph ph-clock-counter-clockwise"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-neutral-900 truncate">{{ $product->name }}</p>
                    <p class="text-xs text-neutral-400">{{ $product->created_at->diffForHumans() }}</p>
                </div>
            </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-neutral-400">{{ __('No recent products.') }}</p>
    @endif
</div>
