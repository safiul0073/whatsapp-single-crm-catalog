@php
    $d = $section->data ?? [];
    $rawLimit = filled($d['product_limit'] ?? null) ? (int) $d['product_limit'] : 4;
    $limit = max(1, min(8, $rawLimit));
    $ctaLink = filled($d['cta_link'] ?? null) ? $d['cta_link'] : route('commerce.products.shortcut');
    $ctaText = filled($d['cta_text'] ?? null) ? $d['cta_text'] : __('View all products');

    $products = \App\Modules\Commerce\Models\Product::query()
        ->with(['workspace', 'primaryMedia', 'category.parent', 'brandRecord'])
        ->withMin([
            'variants as starting_price' => fn ($query) => $query->whereIn('status', ['active', 'out_of_stock']),
        ], 'price')
        ->withSum([
            'variants as stock_total' => fn ($query) => $query->where('status', 'active'),
        ], 'stock_quantity')
        ->where('status', 'active')
        ->whereHas('workspace', fn ($query) => $query
            ->where('settings->commerce->shop_enabled', true)
            ->orWhereNull('settings->commerce->shop_enabled'))
        ->latest('id')
        ->take($limit)
        ->get();

    $workspaceIds = $products->pluck('workspace_id')->unique()->filter()->values()->all();
    $workspacePhones = [];

    if ($workspaceIds !== []) {
        $catalogs = \App\Modules\Commerce\Models\Catalog::query()
            ->with('channelAccount')
            ->whereIn('workspace_id', $workspaceIds)
            ->where('is_active', true)
            ->get();

        foreach ($catalogs as $catalog) {
            $rawPhone = $catalog->channelAccount?->provider_display_id;
            if ($rawPhone) {
                $cleaned = preg_replace('/\D+/', '', (string) $rawPhone);
                if ($cleaned !== '' && ! isset($workspacePhones[$catalog->workspace_id])) {
                    $workspacePhones[$catalog->workspace_id] = $cleaned;
                }
            }
        }

        $missingWorkspaceIds = array_diff($workspaceIds, array_keys($workspacePhones));

        if ($missingWorkspaceIds !== []) {
            $accounts = \App\Modules\MarketingChannels\Models\ChannelAccount::query()
                ->whereIn('workspace_id', $missingWorkspaceIds)
                ->where('provider', 'whatsapp')
                ->where('status', 'connected')
                ->get();

            foreach ($accounts as $account) {
                if ($account->provider_display_id) {
                    $cleaned = preg_replace('/\D+/', '', (string) $account->provider_display_id);
                    if ($cleaned !== '' && ! isset($workspacePhones[$account->workspace_id])) {
                        $workspacePhones[$account->workspace_id] = $cleaned;
                    }
                }
            }
        }
    }
@endphp

@if ($products->isNotEmpty())
    <section class="shop-section bg-bg-section" aria-labelledby="home-products-heading">
        <div class="section-container">
            <div class="shop-section-heading">
                <div>
                    <span>{{ $d['eyebrow_text'] ?? __('WhatsApp Catalog') }}</span>
                    <h2 id="home-products-heading">
                        {{ $d['heading_line_one'] ?? __('Latest') }}
                        <strong class="bg-grad-mark bg-clip-text text-transparent">{{ $d['heading_highlight'] ?? __('products') }}</strong>
                    </h2>
                    @if (! empty($d['subheading']))
                        <p class="mt-4 max-w-2xl text-sm leading-6 text-text-muted">{{ $d['subheading'] }}</p>
                    @endif
                </div>
                <a href="{{ $ctaLink }}" class="product-related-link">
                    {{ $ctaText }}
                    <i class="ph ph-arrow-right"></i>
                </a>
            </div>

            <div class="shop-grid">
                @foreach ($products as $product)
                    @php
                        $startingPrice = $product->starting_price !== null ? (float) $product->starting_price : null;
                        $stockTotal = (int) ($product->stock_total ?? 0);
                        $categoryName = $product->category?->name ?? __('Catalog');
                        $parentCategoryName = $product->category?->parent?->name;
                        $brandName = $product->brandRecord?->name ?? $product->brand;
                        $productCurrency = strtoupper((string) data_get($product->workspace?->settings, 'commerce.currency', 'USD'));
                        $productPhone = $workspacePhones[$product->workspace_id] ?? null;
                        $productUrl = route('commerce.products.public', ['workspace' => $product->workspace->slug, 'product' => $product->slug]);
                        $waText = rawurlencode(__('Hello! I am interested in ordering ":name" (:price) from :shop. Link: :url', [
                            'name' => $product->name,
                            'price' => $startingPrice !== null ? $productCurrency.' '.number_format($startingPrice, 2) : __('Price on request'),
                            'shop' => $product->workspace->name,
                            'url' => $productUrl,
                        ]));
                        $waLink = $productPhone ? "https://wa.me/{$productPhone}?text={$waText}" : $productUrl;
                    @endphp
                    <article class="shop-card">
                        <a href="{{ $productUrl }}" class="shop-card__media" aria-label="{{ __('View :product', ['product' => $product->name]) }}">
                            @if ($product->primaryMedia)
                                <img src="{{ $product->primaryMedia->url }}" alt="{{ $product->primaryMedia->alt ?: $product->name }}" loading="lazy">
                            @else
                                <span><i class="ph ph-t-shirt"></i></span>
                            @endif
                            <span class="shop-card__badge {{ $stockTotal > 0 ? 'is-available' : 'is-limited' }}">
                                <i class="ph {{ $stockTotal > 0 ? 'ph-check-circle' : 'ph-clock' }}"></i>
                                {{ $stockTotal > 0 ? __('In stock') : __('Check availability') }}
                            </span>
                        </a>

                        <div class="shop-card__body">
                            <div class="shop-card__meta">
                                <span><i class="ph ph-storefront"></i> {{ $product->workspace->name }}</span>
                                @if ($brandName)
                                    <span>{{ $brandName }}</span>
                                @endif
                                <span>{{ $parentCategoryName ? $parentCategoryName.' / '.$categoryName : $categoryName }}</span>
                            </div>
                            <h3><a href="{{ $productUrl }}">{{ $product->name }}</a></h3>
                            @if ($product->description)
                                <p>{{ str($product->description)->limit(110) }}</p>
                            @endif
                            <div class="shop-card__footer">
                                <div class="shop-card__price">
                                    <span>{{ __('Starting price') }}</span>
                                    <strong>{{ $startingPrice !== null ? $productCurrency.' '.number_format($startingPrice, 2) : __('Price on request') }}</strong>
                                </div>
                                <a href="{{ $waLink }}" @if ($productPhone) target="_blank" rel="noopener noreferrer" @endif class="{{ $productPhone ? 'shop-card__whatsapp-btn' : 'product-related-link' }}" aria-label="{{ $productPhone ? __('Send WhatsApp message for :product', ['product' => $product->name]) : __('View :product details', ['product' => $product->name]) }}">
                                    <i class="ph {{ $productPhone ? 'ph-whatsapp-logo' : 'ph-arrow-right' }}"></i>
                                    <span>{{ $productPhone ? __('Send WhatsApp Message') : __('View details') }}</span>
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
