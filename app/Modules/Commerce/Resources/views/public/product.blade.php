@extends($layoutView)

@php
    $gallery = $product->gallery
        ->map(fn ($item) => [
            'id' => $item->media_id,
            'type' => $item->media_type,
            'url' => $item->media?->url,
            'alt' => $item->alt_text ?: $product->name,
            'primary' => $item->is_primary,
        ])
        ->filter(fn ($item) => filled($item['url']))
        ->values();

    if ($gallery->isEmpty() && $product->primaryMedia) {
        $gallery = collect([[
            'id' => $product->primaryMedia->id,
            'type' => $product->primaryMedia->type,
            'url' => $product->primaryMedia->url,
            'alt' => $product->primaryMedia->alt ?: $product->name,
            'primary' => true,
        ]]);
    }

    $variants = $product->variants
        ->map(fn ($variant) => [
            'id' => $variant->id,
            'attributes' => $variant->attributes ?? [],
            'price' => (float) $variant->price,
            'compare_at_price' => $variant->compare_at_price ? (float) $variant->compare_at_price : null,
            'stock' => $variant->stock_quantity,
            'status' => $variant->status,
            'media_id' => $variant->media_id,
        ])
        ->values();

    $firstVariant = $variants->first();
    $startingPrice = $firstVariant['price'] ?? null;
    $brandName = $product->brandRecord?->name ?? $product->brand;
    $categoryName = $product->category?->name ?? __('Product');
    $currency = $currency ?? 'USD';
    $relatedProducts = $relatedProducts ?? collect([]);
@endphp

@section('title', __(':product — :name', ['product' => $product->name, 'name' => $themeVars['logo_text'] ?? config('app.name')]))
@section('meta_description', str($product->description ?: $product->name)->limit(155))

@section('main')
    <article
        class="product-public"
        aria-labelledby="product-public-heading"
        x-data="{
            gallery: @js($gallery),
            variants: @js($variants),
            productName: @js($product->name),
            activeMedia: 0,
            selectedVariant: 0,
            quantity: 1,
            zoomOpen: false,
            isHovering: false,
            zoomX: 50,
            zoomY: 50,
            handleMouseMove(e) {
                const rect = e.currentTarget.getBoundingClientRect();
                this.zoomX = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100));
                this.zoomY = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100));
            },
            selectVariant(index) {
                this.selectedVariant = index;
                const mediaId = this.variants[index]?.media_id;
                if (mediaId) {
                    const mediaIndex = this.gallery.findIndex(item => String(item.id) === String(mediaId));
                    if (mediaIndex >= 0) {
                        this.activeMedia = mediaIndex;
                    }
                }
            },
            nextMedia() {
                if (this.gallery.length > 0) {
                    this.activeMedia = (this.activeMedia + 1) % this.gallery.length;
                }
            },
            prevMedia() {
                if (this.gallery.length > 0) {
                    this.activeMedia = (this.activeMedia - 1 + this.gallery.length) % this.gallery.length;
                }
            },
            money(value) {
                return new Intl.NumberFormat('en-US', { style: 'currency', currency: @js($currency) }).format(value || 0);
            },
            selectedAttributes() {
                return Object.values(this.variants[this.selectedVariant]?.attributes || {}).join(' / ');
            },
            unitPrice() {
                return this.variants[this.selectedVariant]?.price || @js($startingPrice) || 0;
            },
            totalPrice() {
                return this.unitPrice() * this.quantity;
            }
        }"
    >
        <!-- Fullscreen Lightbox Modal -->
        <div
            x-show="zoomOpen"
            x-cloak
            x-transition.opacity
            class="product-lightbox"
            @keydown.escape.window="zoomOpen = false"
        >
            <div class="product-lightbox__backdrop" @click="zoomOpen = false"></div>
            <div class="product-lightbox__dialog">
                <header class="product-lightbox__header">
                    <div>
                        <span x-text="`Media ${activeMedia + 1} of ${gallery.length}`"></span>
                        <strong x-text="productName"></strong>
                    </div>
                    <button type="button" @click="zoomOpen = false" aria-label="{{ __('Close gallery modal') }}">
                        <i class="ph ph-x"></i>
                    </button>
                </header>

                <div class="product-lightbox__stage">
                    <template x-for="(item, index) in gallery" :key="item.id">
                        <div x-show="activeMedia === index" class="product-lightbox__content">
                            <template x-if="item.type === 'image'">
                                <img :src="item.url" :alt="item.alt">
                            </template>
                            <template x-if="item.type === 'video'">
                                <video :src="item.url" controls autoplay></video>
                            </template>
                        </div>
                    </template>

                    <button type="button" class="product-lightbox__nav product-lightbox__nav--prev" @click="prevMedia" x-show="gallery.length > 1" aria-label="{{ __('Previous media') }}">
                        <i class="ph ph-caret-left"></i>
                    </button>
                    <button type="button" class="product-lightbox__nav product-lightbox__nav--next" @click="nextMedia" x-show="gallery.length > 1" aria-label="{{ __('Next media') }}">
                        <i class="ph ph-caret-right"></i>
                    </button>
                </div>

                <div class="product-lightbox__thumbs" x-show="gallery.length > 1">
                    <template x-for="(item, index) in gallery" :key="'lightbox-'+item.id">
                        <button type="button" :class="activeMedia === index ? 'is-active' : ''" @click="activeMedia = index">
                            <template x-if="item.type === 'image'">
                                <img :src="item.url" :alt="item.alt">
                            </template>
                            <template x-if="item.type === 'video'">
                                <span><i class="ph ph-play-circle"></i></span>
                            </template>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <header class="product-public__hero">
            <div class="section-container">
                <div class="product-public__breadcrumb">
                    <a href="{{ route('commerce.products.shortcut') }}">{{ __('Catalog') }}</a>
                    <span><a href="{{ route('commerce.products.index', $workspace->slug) }}">{{ $workspace->name }}</a></span>
                    <span>{{ $categoryName }}</span>
                </div>
                <div class="product-public__heading">
                    <div>
                        <div class="product-public__tags">
                            <span class="product-public__store-tag"><i class="ph ph-storefront"></i> {{ $workspace->name }}</span>
                            @if ($brandName)
                                <span class="product-public__brand-tag">{{ $brandName }}</span>
                            @endif
                        </div>
                        <h1 id="product-public-heading">{{ $product->name }}</h1>
                        @if ($product->description)
                            <p class="product-public__subtext">{{ str($product->description)->limit(180) }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        <div class="product-public__body">
            <div class="section-container">
                <div class="product-public__grid">
                    <!-- Media Gallery with Zoom System -->
                    <section class="product-gallery" aria-label="{{ __('Product media gallery') }}">
                        <div
                            class="product-gallery__stage"
                            @mousemove="handleMouseMove($event)"
                            @mouseenter="isHovering = true"
                            @mouseleave="isHovering = false"
                        >
                            <template x-for="(item, index) in gallery" :key="item.id">
                                <div x-show="activeMedia === index" class="product-gallery__item" x-cloak>
                                    <template x-if="item.type === 'image'">
                                        <div class="product-gallery__zoom-wrap" @click="zoomOpen = true">
                                            <img
                                                :src="item.url"
                                                :alt="item.alt"
                                                :style="isHovering ? `transform-origin: ${zoomX}% ${zoomY}%; transform: scale(2.2); cursor: zoom-in;` : 'transform: scale(1); cursor: zoom-in;'"
                                                class="product-gallery__image"
                                            >
                                        </div>
                                    </template>
                                    <template x-if="item.type === 'video'">
                                        <video :src="item.url" controls preload="metadata"></video>
                                    </template>
                                </div>
                            </template>
                            <div class="product-gallery__empty" x-show="gallery.length === 0">
                                <i class="ph ph-t-shirt"></i>
                            </div>

                            <div class="product-gallery__stage-actions" x-show="gallery.length > 0">
                                <button type="button" class="product-gallery__expand-btn" @click="zoomOpen = true">
                                    <i class="ph ph-arrows-out-cardinal"></i>
                                    <span>{{ __('Expand Lightbox') }}</span>
                                </button>
                                <span class="product-gallery__zoom-badge" x-show="!isHovering">
                                    <i class="ph ph-magnifying-glass-plus"></i> {{ __('Hover to zoom') }}
                                </span>
                            </div>

                            <template x-if="gallery.length > 1">
                                <div class="product-gallery__stage-nav">
                                    <button type="button" class="product-gallery__nav-btn product-gallery__nav-btn--prev" @click="prevMedia" aria-label="{{ __('Previous image') }}">
                                        <i class="ph ph-caret-left"></i>
                                    </button>
                                    <button type="button" class="product-gallery__nav-btn product-gallery__nav-btn--next" @click="nextMedia" aria-label="{{ __('Next image') }}">
                                        <i class="ph ph-caret-right"></i>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <div class="product-gallery__thumbs" x-show="gallery.length > 1">
                            <template x-for="(item, index) in gallery" :key="item.id">
                                <button type="button" :class="activeMedia === index ? 'is-active' : ''" @click="activeMedia = index" :aria-label="`View media ${index + 1}`">
                                    <template x-if="item.type === 'image'">
                                        <img :src="item.url" :alt="item.alt">
                                    </template>
                                    <template x-if="item.type === 'video'">
                                        <span><i class="ph ph-play-circle"></i></span>
                                    </template>
                                </button>
                            </template>
                        </div>
                    </section>

                    <!-- Buy Box Sidebar -->
                    <aside class="product-buy-box">
                        <div class="product-buy-box__header">
                            <span class="product-stock" :class="(variants[selectedVariant]?.stock || 0) > 0 ? 'is-available' : 'is-limited'">
                                <span class="product-stock__dot"></span>
                                <span x-text="(variants[selectedVariant]?.stock || 0) > 0 ? '{{ __('In Stock & Ready to Order') }}' : '{{ __('Check Availability') }}'"></span>
                            </span>

                            <div class="product-buy-box__price-tag">
                                <span class="product-buy-box__label">{{ __('Price') }}</span>
                                <div class="product-buy-box__amount">
                                    <strong x-text="money(variants[selectedVariant]?.price || {{ $startingPrice ?? 0 }})"></strong>
                                </div>
                            </div>
                        </div>

                        <!-- Variant Option Selector Chips -->
                        <div class="product-variants" x-show="variants.length > 0">
                            <h2>{{ __('Select Variant') }}</h2>
                            <div class="product-variants__grid">
                                <template x-for="(variant, index) in variants" :key="variant.id">
                                    <button
                                        type="button"
                                        class="product-variant-chip"
                                        :class="selectedVariant === index ? 'is-active' : ''"
                                        @click="selectVariant(index)"
                                    >
                                        <div class="product-variant-chip__attrs">
                                            <template x-for="(value, key) in variant.attributes" :key="key">
                                                <span class="product-variant-chip__tag">
                                                    <span class="product-variant-chip__key" x-text="key + ':'"></span>
                                                    <strong x-text="value"></strong>
                                                </span>
                                            </template>
                                        </div>
                                        <span class="product-variant-chip__price" x-text="money(variant.price)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Quantity Selector -->
                        <div class="product-quantity">
                            <label for="product-quantity-input">{{ __('Quantity') }}</label>
                            <div class="product-quantity__controls">
                                <button type="button" @click="if (quantity > 1) quantity--" :disabled="quantity <= 1" aria-label="Decrease quantity">
                                    <i class="ph ph-minus"></i>
                                </button>
                                <input id="product-quantity-input" type="number" min="1" max="99" x-model.number="quantity">
                                <button type="button" @click="quantity++" aria-label="Increase quantity">
                                    <i class="ph ph-plus"></i>
                                </button>
                                <span class="product-quantity__total" x-show="quantity > 1">
                                    {{ __('Total:') }} <strong x-text="money(totalPrice())"></strong>
                                </span>
                            </div>
                        </div>

                        <!-- Direct WhatsApp Order Button -->
                        @if ($whatsappPhone)
                            <a
                                class="product-whatsapp-cta"
                                :href="`https://wa.me/{{ $whatsappPhone }}?text=${encodeURIComponent('Hello! I would like to order ' + quantity + 'x ' + productName + (selectedAttributes() ? ' (' + selectedAttributes() + ')' : '') + ' for total ' + money(totalPrice()) + '. Product link: ' + window.location.href)}`"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <i class="ph ph-whatsapp-logo"></i>
                                <div class="product-whatsapp-cta__text">
                                    <strong>{{ __('Order on WhatsApp') }}</strong>
                                    <span>{{ __('Direct store confirmation & fast support') }}</span>
                                </div>
                            </a>
                        @else
                            <a
                                class="product-whatsapp-cta product-whatsapp-cta--general"
                                href="{{ route('commerce.products.index', $workspace->slug) }}"
                            >
                                <i class="ph ph-storefront"></i>
                                <div class="product-whatsapp-cta__text">
                                    <strong>{{ __('Contact Merchant Store') }}</strong>
                                    <span>{{ __('Browse store catalog') }}</span>
                                </div>
                            </a>
                        @endif

                        <!-- Trust Badges Grid -->
                        <div class="product-trust-grid">
                            <div class="product-trust-item">
                                <i class="ph ph-whatsapp-logo"></i>
                                <div>
                                    <strong>{{ __('WhatsApp Order') }}</strong>
                                    <p>{{ __('Instant response & support') }}</p>
                                </div>
                            </div>
                            <div class="product-trust-item">
                                <i class="ph ph-truck"></i>
                                <div>
                                    <strong>{{ __('Direct Delivery') }}</strong>
                                    <p>{{ __('Confirmed before payment') }}</p>
                                </div>
                            </div>
                            <div class="product-trust-item">
                                <i class="ph ph-shield-check"></i>
                                <div>
                                    <strong>{{ __('Verified Store') }}</strong>
                                    <p>{{ $workspace->name }}</p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>

                <!-- Product Specifications Matrix & Details -->
                <div class="product-details-content">
                    <div class="product-details-tabs">
                        <section class="product-spec-card">
                            <h2><i class="ph ph-article"></i> {{ __('Product Description') }}</h2>
                            <div class="product-description-text">
                                <p>{{ $product->description ?: __('No detailed description provided.') }}</p>
                            </div>
                        </section>

                        <section class="product-spec-card">
                            <h2><i class="ph ph-list-bullets"></i> {{ __('Specifications & Facts') }}</h2>
                            <dl class="product-facts-grid">
                                <div>
                                    <dt>{{ __('Store') }}</dt>
                                    <dd><a href="{{ route('commerce.products.index', $workspace->slug) }}">{{ $workspace->name }}</a></dd>
                                </div>
                                <div>
                                    <dt>{{ __('Category') }}</dt>
                                    <dd>{{ $categoryName }}</dd>
                                </div>
                                @if ($brandName)
                                    <div>
                                        <dt>{{ __('Brand') }}</dt>
                                        <dd>{{ $brandName }}</dd>
                                    </div>
                                @endif
                                @if ($product->condition)
                                    <div>
                                        <dt>{{ __('Condition') }}</dt>
                                        <dd>{{ str($product->condition)->replace('_', ' ')->title() }}</dd>
                                    </div>
                                @endif
                                @if ($product->country_of_origin)
                                    <div>
                                        <dt>{{ __('Origin') }}</dt>
                                        <dd>{{ $product->country_of_origin }}</dd>
                                    </div>
                                @endif
                            </dl>

                            @if ($product->care_information)
                                <div class="product-care-box">
                                    <h3><i class="ph ph-heartbeat"></i> {{ __('Care Information') }}</h3>
                                    <p>{{ $product->care_information }}</p>
                                </div>
                            @endif
                        </section>
                    </div>
                </div>

                <!-- Related Products Section -->
                @if ($relatedProducts->isNotEmpty())
                    <section class="product-related-section">
                        <div class="product-related-heading">
                            <div>
                                <span>{{ __('Store Showcase') }}</span>
                                <h2>{{ __('More from :shop', ['shop' => $workspace->name]) }}</h2>
                            </div>
                            <a href="{{ route('commerce.products.index', $workspace->slug) }}" class="product-related-link">
                                {{ __('View store catalog') }} <i class="ph ph-arrow-right"></i>
                            </a>
                        </div>

                        <div class="shop-grid">
                            @foreach ($relatedProducts as $relProduct)
                                @php
                                    $relPrice = $relProduct->starting_price !== null ? (float) $relProduct->starting_price : null;
                                    $relStock = (int) ($relProduct->stock_total ?? 0);
                                    $relUrl = route('commerce.products.public', ['workspace' => $workspace->slug, 'product' => $relProduct->slug]);
                                    $relWaText = rawurlencode(__('Hello! I am interested in ordering ":name" from :shop. Link: :url', [
                                        'name' => $relProduct->name,
                                        'shop' => $workspace->name,
                                        'url' => $relUrl,
                                    ]));
                                    $relWaLink = $whatsappPhone ? "https://wa.me/{$whatsappPhone}?text={$relWaText}" : $relUrl;
                                @endphp
                                <article class="shop-card">
                                    <a href="{{ $relUrl }}" class="shop-card__media" aria-label="{{ __('View :product', ['product' => $relProduct->name]) }}">
                                        @if ($relProduct->primaryMedia)
                                            <img src="{{ $relProduct->primaryMedia->url }}" alt="{{ $relProduct->primaryMedia->alt ?: $relProduct->name }}" loading="lazy">
                                        @else
                                            <span><i class="ph ph-t-shirt"></i></span>
                                        @endif
                                        <span class="shop-card__badge {{ $relStock > 0 ? 'is-available' : 'is-limited' }}">
                                            <i class="ph {{ $relStock > 0 ? 'ph-check-circle' : 'ph-clock' }}"></i>
                                            {{ $relStock > 0 ? __('In stock') : __('Check availability') }}
                                        </span>
                                    </a>

                                    <div class="shop-card__body">
                                        <div class="shop-card__meta">
                                            <span>{{ $relProduct->category?->name ?? __('Catalog') }}</span>
                                        </div>
                                        <h3><a href="{{ $relUrl }}">{{ $relProduct->name }}</a></h3>
                                        <div class="shop-card__footer">
                                            <div class="shop-card__price">
                                                <span>{{ __('Starting price') }}</span>
                                                <strong>{{ $relPrice !== null ? $currency.' '.number_format($relPrice, 2) : __('Price on request') }}</strong>
                                            </div>
                                            <a href="{{ $relWaLink }}" @if ($whatsappPhone) target="_blank" rel="noopener noreferrer" @endif class="shop-card__whatsapp-btn">
                                                <i class="ph ph-whatsapp-logo"></i>
                                                <span>{{ __('Send WhatsApp Message') }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </article>
@endsection
