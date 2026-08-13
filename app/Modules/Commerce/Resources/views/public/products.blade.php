@extends($layoutView)

@section('title', __('Products — :name', ['name' => $themeVars['logo_text'] ?? config('app.name')]))
@section('meta_description', __('Browse active products from available WhatsApp storefronts.'))

@section('main')
    <section class="shop-hero" aria-labelledby="all-products-heading">
        <div class="section-container">
            <div class="shop-hero__content">
                <span>{{ __('Products') }}</span>
                <h1 id="all-products-heading">{{ __('All active products') }}</h1>
                <p>{{ __('Browse products across public shops, filter by category, and open the merchant storefront when you are ready to order.') }}</p>
            </div>
        </div>
    </section>

    <section class="shop-section" aria-labelledby="all-products-list-heading">
        <div class="section-container">
            <nav class="shop-filter" aria-label="{{ __('Product category filters') }}">
                <a href="{{ route('commerce.products.shortcut') }}" class="shop-filter__link {{ ! $selectedCategoryId && ! $selectedSubcategoryId ? 'is-active' : '' }}">
                    {{ __('All products') }}
                </a>

                @foreach ($categories as $category)
                    @php
                        $isCategoryActive = (int) $selectedCategoryId === (int) $category->id && ! $selectedSubcategoryId;
                        $hasActiveChild = $category->children->contains(fn ($child): bool => (int) $child->id === (int) $selectedSubcategoryId);
                    @endphp
                    <div class="shop-filter__group">
                        <a
                            href="{{ route('commerce.products.shortcut', ['category' => $category->id]) }}"
                            class="shop-filter__link {{ $isCategoryActive || $hasActiveChild ? 'is-active' : '' }}"
                        >
                            {{ $category->name }}
                            @if ($category->children->isNotEmpty())
                                <i class="ph ph-caret-down"></i>
                            @endif
                        </a>

                        @if ($category->children->isNotEmpty())
                            <div class="shop-filter__mega">
                                <div>
                                    <p>{{ $category->name }}</p>
                                    <span>{{ trans_choice(':count subcategory|:count subcategories', $category->children->count(), ['count' => $category->children->count()]) }}</span>
                                </div>
                                <div class="shop-filter__children">
                                    <a href="{{ route('commerce.products.shortcut', ['category' => $category->id]) }}" class="{{ $isCategoryActive ? 'is-active' : '' }}">
                                        <i class="ph ph-grid-four"></i>
                                        {{ __('All :category', ['category' => $category->name]) }}
                                    </a>
                                    @foreach ($category->children as $child)
                                        <a
                                            href="{{ route('commerce.products.shortcut', ['category' => $category->id, 'subcategory' => $child->id]) }}"
                                            class="{{ (int) $selectedSubcategoryId === (int) $child->id ? 'is-active' : '' }}"
                                        >
                                            <i class="ph ph-tag"></i>
                                            {{ $child->name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </nav>

            <div class="shop-section-heading">
                <div>
                    <span>{{ __('Catalog') }}</span>
                    <h2 id="all-products-list-heading">{{ __('Latest products') }}</h2>
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
                            $parentCategoryName = $product->category?->parent?->name;
                            $brandName = $product->brandRecord?->name ?? $product->brand;
                            $productCurrency = strtoupper((string) data_get($product->workspace?->settings, 'commerce.currency', $currency));
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
                                    <a href="{{ $waLink }}" @if ($productPhone) target="_blank" rel="noopener noreferrer" @endif class="shop-card__whatsapp-btn" aria-label="{{ __('Send WhatsApp message for :product', ['product' => $product->name]) }}">
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
                    <h2>{{ __('No products match this filter') }}</h2>
                    <p>{{ __('Choose another category or clear the filters to browse every active product.') }}</p>
                </div>
            @endif
        </div>
    </section>
@endsection
