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
                <p>{{ trans_choice(':count product available|:count products available', $products->total(), ['count' => $products->total()]) }}</p>
            </div>

            @if ($products->isNotEmpty())
                <div class="shop-grid">
                    @foreach ($products as $product)
                        @php
                            $startingPrice = $product->starting_price !== null ? (float) $product->starting_price : null;
                            $stockTotal = (int) ($product->stock_total ?? 0);
                            $categoryName = $product->category?->name ?? __('Uncategorized');
                            $brandName = $product->brandRecord?->name ?? $product->brand;
                        @endphp
                        <article class="shop-card">
                            <a href="{{ route('commerce.products.public', ['workspace' => $workspace->slug, 'product' => $product->slug]) }}" class="shop-card__media" aria-label="{{ __('View :product', ['product' => $product->name]) }}">
                                @if ($product->primaryMedia)
                                    <img src="{{ $product->primaryMedia->url }}" alt="{{ $product->primaryMedia->alt ?: $product->name }}" loading="lazy">
                                @else
                                    <span><i class="ph ph-t-shirt"></i></span>
                                @endif
                            </a>

                            <div class="shop-card__body">
                                <div class="shop-card__meta">
                                    @if ($brandName)
                                        <span>{{ $brandName }}</span>
                                    @endif
                                    <span>{{ $categoryName }}</span>
                                </div>
                                <h3><a href="{{ route('commerce.products.public', ['workspace' => $workspace->slug, 'product' => $product->slug]) }}">{{ $product->name }}</a></h3>
                                @if ($product->description)
                                    <p>{{ str($product->description)->limit(120) }}</p>
                                @endif
                                <div class="shop-card__footer">
                                    <strong>{{ $startingPrice !== null ? __('From :currency :price', ['currency' => $currency, 'price' => number_format($startingPrice, 2)]) : __('Price on request') }}</strong>
                                    <span class="{{ $stockTotal > 0 ? 'is-available' : 'is-limited' }}">
                                        {{ $stockTotal > 0 ? __('In stock') : __('Check availability') }}
                                    </span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($products->hasPages())
                    <div class="shop-pagination">
                        {{ $products->links() }}
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
