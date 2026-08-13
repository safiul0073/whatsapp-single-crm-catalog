@extends($layoutView)

@section('title', __(':shop — :name', ['shop' => data_get($workspace->settings, 'commerce.storefront_title', $workspace->name), 'name' => $themeVars['logo_text'] ?? config('app.name')]))
@section('meta_description', data_get($workspace->settings, 'commerce.storefront_description', __('Browse active products available for WhatsApp ordering.')))

@section('main')
    <section class="shop-hero" aria-labelledby="shop-heading">
        <div class="section-container">
            <div class="shop-hero__content">
                <span>{{ __('Shop') }}</span>
                <h1 id="shop-heading">{{ data_get($workspace->settings, 'commerce.storefront_title', $workspace->name) }}</h1>
                <p>{{ data_get($workspace->settings, 'commerce.storefront_description', __('Browse active catalog items, compare available variants, and start a direct WhatsApp order when you are ready.')) }}</p>
            </div>
        </div>
    </section>

    <section class="shop-section" aria-labelledby="shop-products-heading">
        <div class="section-container">
            <div class="shop-section-heading">
                <div>
                    <span>{{ __('Catalog') }}</span>
                    <h2 id="shop-products-heading">{{ __('Active products') }}</h2>
                </div>
                <p>{{ __('Showing :first–:last of :total products', ['first' => $products->firstItem() ?? 0, 'last' => $products->lastItem() ?? 0, 'total' => $products->total()]) }}</p>
            </div>

            @if ($products->isNotEmpty())
                <div class="shop-grid">
                    @foreach ($products as $product)
                        @php
                            $startingPrice = $product->starting_price !== null ? (float) $product->starting_price : null;
                            $stockTotal = (int) ($product->stock_total ?? 0);
                            $categoryName = $product->category?->name ?? __('Uncategorized');
                            $brandName = $product->brandRecord?->name ?? $product->brand;
                            $productUrl = route('commerce.products.public', ['workspace' => $workspace->slug, 'product' => $product->slug]);
                            $waText = rawurlencode(__('Hello! I am interested in ordering ":name" (:price) from :shop. Link: :url', [
                                'name' => $product->name,
                                'price' => $startingPrice !== null ? $currency.' '.number_format($startingPrice, 2) : __('Price on request'),
                                'shop' => $workspace->name,
                                'url' => $productUrl,
                            ]));
                            $waLink = $whatsappPhone ? "https://wa.me/{$whatsappPhone}?text={$waText}" : $productUrl;
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
                                    @if ($brandName)
                                        <span>{{ $brandName }}</span>
                                    @endif
                                    <span>{{ $categoryName }}</span>
                                </div>
                                <h3><a href="{{ $productUrl }}">{{ $product->name }}</a></h3>
                                @if ($product->description)
                                    <p>{{ str($product->description)->limit(110) }}</p>
                                @endif
                                <div class="shop-card__footer">
                                    <div class="shop-card__price">
                                        <span>{{ __('Starting price') }}</span>
                                        <strong>{{ $startingPrice !== null ? $currency.' '.number_format($startingPrice, 2) : __('Price on request') }}</strong>
                                    </div>
                                    <a href="{{ $waLink }}" @if ($whatsappPhone) target="_blank" rel="noopener noreferrer" @endif class="shop-card__whatsapp-btn" aria-label="{{ __('Send WhatsApp message for :product', ['product' => $product->name]) }}">
                                        <i class="ph ph-whatsapp-logo"></i>
                                        <span>{{ __('Send WhatsApp Message') }}</span>
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($products->hasPages())
                    <div class="shop-pagination">
                        <div class="shop-pagination__info">
                            {{ __('Showing page :current of :last (:total items)', ['current' => $products->currentPage(), 'last' => $products->lastPage(), 'total' => $products->total()]) }}
                        </div>
                        <div class="shop-pagination__links">
                            {{ $products->links() }}
                        </div>
                    </div>
                @endif
            @else
                <div class="shop-empty">
                    <span><i class="ph ph-shopping-bag-open"></i></span>
                    <h2>{{ __('No active products yet') }}</h2>
                    <p>{{ __('Published products will appear here as soon as they are active.') }}</p>
                </div>
            @endif
        </div>
    </section>
@endsection
