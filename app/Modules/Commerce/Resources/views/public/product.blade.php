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
            'sku' => $variant->sku ?: ('PRD-'.str_pad((string) $variant->id, 6, '0', STR_PAD_LEFT)),
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
    $categoryName = $product->category?->name ?? __('Catalog');
    $currency = $currency ?? 'USD';
    $currencySymbol = match(strtoupper($currency)) {
        'BDT' => '৳',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        default => $currency,
    };
    $productCode = $firstVariant['sku'] ?? ('PRD-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT));
    $options = $options ?? collect([]);
    $relatedProducts = $relatedProducts ?? collect([]);
@endphp

@section('title', __(':product — :name', ['product' => $product->name, 'name' => $themeVars['logo_text'] ?? config('app.name')]))
@section('meta_description', str($product->description ?: $product->name)->limit(155))

@section('main')
    <article
        class="product-detail-page"
        aria-labelledby="product-page-heading"
        x-data="{
            gallery: @js($gallery),
            variants: @js($variants),
            options: @js($options),
            productName: @js($product->name),
            currencySymbol: @js($currencySymbol),
            activeMedia: 0,
            selectedVariant: 0,
            selectedOptions: {},
            quantity: 1,
            zoomOpen: false,
            isHovering: false,
            copied: false,
            zoomX: 50,
            zoomY: 50,
            activeTab: 'details',
            init() {
                if (this.options && this.options.length > 0) {
                    const firstAttrs = this.variants[0]?.attributes || {};
                    this.options.forEach(opt => {
                        this.selectedOptions[opt.code] = firstAttrs[opt.code] || (opt.values && opt.values[0]) || '';
                    });
                }
            },
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
                const attrs = this.variants[index]?.attributes || {};
                Object.keys(attrs).forEach(k => {
                    this.selectedOptions[k] = attrs[k];
                });
            },
            selectOption(code, val) {
                this.selectedOptions[code] = val;
                const matchIndex = this.variants.findIndex(v => {
                    return Object.entries(this.selectedOptions).every(([k, vVal]) => {
                        if (!vVal) return true;
                        return String(v.attributes[k] || '').toLowerCase() === String(vVal).toLowerCase();
                    });
                });
                if (matchIndex >= 0) {
                    this.selectVariant(matchIndex);
                }
            },
            nextMedia() {
                if (this.gallery.length > 0) {
                    this.activeMedia = (this.activeMedia + 1) % this.gallery.length;
                    this.scrollThumbIntoView(this.activeMedia);
                }
            },
            prevMedia() {
                if (this.gallery.length > 0) {
                    this.activeMedia = (this.activeMedia - 1 + this.gallery.length) % this.gallery.length;
                    this.scrollThumbIntoView(this.activeMedia);
                }
            },
            scrollThumbIntoView(idx) {
                const container = this.$refs.thumbTrack;
                if (container) {
                    const thumb = container.children[idx];
                    if (thumb) {
                        thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }
                }
            },
            money(value) {
                const num = Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                return `${this.currencySymbol} ${num}`;
            },
            selectedAttributes() {
                return Object.values(this.variants[this.selectedVariant]?.attributes || {}).join(' / ');
            },
            unitPrice() {
                return this.variants[this.selectedVariant]?.price || @js($startingPrice) || 0;
            },
            totalPrice() {
                return this.unitPrice() * this.quantity;
            },
            currentSku() {
                return this.variants[this.selectedVariant]?.sku || @js($productCode);
            },
            openLightbox(index = null) {
                if (index !== null) {
                    this.activeMedia = index;
                    this.scrollThumbIntoView(index);
                }
                this.zoomOpen = true;
            },
            closeLightbox() {
                this.zoomOpen = false;
            },
            copyLink() {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(window.location.href);
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 2500);
                }
            }
        }"
    >
        <!-- Fullscreen Lightbox Modal with Ambient Blur Backdrop -->
        <div
            x-show="zoomOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="product-lightbox"
            @keydown.escape.window="closeLightbox()"
        >
            <div class="product-lightbox__backdrop" @click="closeLightbox()"></div>
            <div class="product-lightbox__dialog">
                <header class="product-lightbox__header">
                    <div>
                        <span x-text="`Media ${activeMedia + 1} of ${gallery.length}`"></span>
                        <strong x-text="productName"></strong>
                    </div>
                    <button type="button" @click="closeLightbox()" aria-label="{{ __('Close gallery') }}">
                        <i class="ph ph-x"></i>
                    </button>
                </header>

                <div class="product-lightbox__stage">
                    <!-- Media backdrop -->
                    <div
                        class="product-lightbox__ambient"
                        :style="gallery[activeMedia]?.url ? `background-image: url('${gallery[activeMedia]?.url}');` : ''"
                    ></div>

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

                    <button type="button" class="product-lightbox__nav product-lightbox__nav--prev" @click="prevMedia" x-show="gallery.length > 1" aria-label="{{ __('Previous') }}">
                        <i class="ph ph-caret-left"></i>
                    </button>
                    <button type="button" class="product-lightbox__nav product-lightbox__nav--next" @click="nextMedia" x-show="gallery.length > 1" aria-label="{{ __('Next') }}">
                        <i class="ph ph-caret-right"></i>
                    </button>
                </div>

                <div class="product-lightbox__thumbs" x-show="gallery.length > 1">
                    <template x-for="(item, index) in gallery" :key="'lightbox-'+item.id">
                        <button type="button" :class="activeMedia === index ? 'is-active' : ''" @click="activeMedia = index; scrollThumbIntoView(index)">
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

        <!-- Breadcrumb Navigation -->
        <nav class="product-page-breadcrumb" aria-label="{{ __('Breadcrumb') }}">
            <div class="section-container">
                <ol class="product-page-breadcrumb__list">
                    <li><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
                    <li><a href="{{ route('commerce.products.shortcut') }}">{{ __('Catalog') }}</a></li>
                    <li><a href="{{ route('commerce.products.index', $workspace->slug) }}">{{ $workspace->name }}</a></li>
                    <li aria-current="page"><span>{{ $product->name }}</span></li>
                </ol>
            </div>
        </nav>

        <!-- Main Product Section -->
        <section class="product-page-main">
            <div class="section-container">
                <div class="product-regal-grid">

                    <!-- Product media gallery -->
                    <div class="product-regal-gallery">
                        <div
                            class="product-regal-stage"
                            @mousemove="handleMouseMove($event)"
                            @mouseenter="isHovering = true"
                            @mouseleave="isHovering = false"
                        >
                            <template x-for="(item, index) in gallery" :key="item.id">
                                <div x-show="activeMedia === index" class="product-regal-stage__item" x-cloak>
                                    <template x-if="item.type === 'image'">
                                        <div class="product-regal-stage__zoom-wrap" @click="openLightbox(activeMedia)">
                                            <img
                                                :src="item.url"
                                                :alt="item.alt"
                                                :style="isHovering ? `transform-origin: ${zoomX}% ${zoomY}%; transform: scale(2.6); cursor: zoom-in;` : 'transform: scale(1); cursor: zoom-in;'"
                                                class="product-regal-stage__img"
                                            >
                                        </div>
                                    </template>
                                    <template x-if="item.type === 'video'">
                                        <video :src="item.url" controls preload="metadata"></video>
                                    </template>
                                </div>
                            </template>
                            <div class="product-regal-stage__empty" x-show="gallery.length === 0">
                                <i class="ph ph-t-shirt"></i>
                            </div>

                            <button type="button" class="product-regal-stage__expand-btn" @click="openLightbox(activeMedia)" title="{{ __('Expand Gallery') }}">
                                <i class="ph ph-arrows-out"></i>
                            </button>
                        </div>

                        <div class="product-regal-thumbs-slider" x-show="gallery.length > 1">
                            <button type="button" class="product-regal-thumbs__nav product-regal-thumbs__nav--prev" @click="prevMedia" aria-label="{{ __('Previous thumbnail') }}">
                                <i class="ph ph-caret-left"></i>
                            </button>

                            <div class="product-regal-thumbs__track" x-ref="thumbTrack">
                                <template x-for="(item, index) in gallery" :key="'thumb-'+item.id">
                                    <div class="product-regal-thumbs__wrapper">
                                        <button
                                            type="button"
                                            class="product-regal-thumbs__item"
                                            :class="activeMedia === index ? 'is-active' : ''"
                                            @click="if (activeMedia === index) { openLightbox(index); } else { activeMedia = index; scrollThumbIntoView(index); }"
                                            @dblclick="openLightbox(index)"
                                            :aria-label="`Media ${index + 1}`"
                                        >
                                            <template x-if="item.type === 'image'">
                                                <img :src="item.url" :alt="item.alt">
                                            </template>
                                            <template x-if="item.type === 'video'">
                                                <span><i class="ph ph-play-circle"></i></span>
                                            </template>
                                        </button>
                                        <button
                                            type="button"
                                            class="product-regal-thumbs__zoom-icon"
                                            @click.stop="openLightbox(index)"
                                            title="{{ __('Enlarge image') }}"
                                        >
                                            <i class="ph ph-magnifying-glass-plus"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <button type="button" class="product-regal-thumbs__nav product-regal-thumbs__nav--next" @click="nextMedia" aria-label="{{ __('Next thumbnail') }}">
                                <i class="ph ph-caret-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Product details and buying actions -->
                    <div class="product-regal-info">
                        <h1 id="product-page-heading" class="product-regal-info__title">{{ $product->name }}</h1>

                        <div class="product-regal-info__sku">
                            <span x-text="currentSku()">{{ $productCode }}</span>
                        </div>

                        <div class="product-regal-info__code">
                            <span>{{ __('Product Code :') }} <strong x-text="currentSku()">{{ $productCode }}</strong></span>
                        </div>

                        <div class="product-regal-info__price-box">
                            <span class="product-regal-info__price" x-text="money(variants[selectedVariant]?.price || {{ $startingPrice ?? 0 }})">
                                {{ $currencySymbol }} {{ number_format($startingPrice ?? 0, 0) }}
                            </span>
                            <template x-if="variants[selectedVariant]?.compare_at_price > variants[selectedVariant]?.price">
                                <span class="product-regal-info__price-compare" x-text="money(variants[selectedVariant]?.compare_at_price)"></span>
                            </template>
                        </div>

                        <hr class="product-regal-info__divider">

                        <template x-if="options && options.length > 0">
                            <div class="product-regal-options">
                                <template x-for="opt in options" :key="opt.code">
                                    <div class="product-regal-option-group">
                                        <label class="product-regal-option-group__label">
                                            <span x-text="opt.name"></span>: <strong x-text="selectedOptions[opt.code]"></strong>
                                        </label>
                                        <div class="product-regal-option-group__pills">
                                            <template x-for="val in opt.values" :key="val">
                                                <button
                                                    type="button"
                                                    class="product-regal-pill"
                                                    :class="selectedOptions[opt.code] === val ? 'is-active' : ''"
                                                    @click="selectOption(opt.code, val)"
                                                    x-text="val"
                                                ></button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="(!options || options.length === 0) && variants.length > 0">
                            <div class="product-regal-options">
                                <label class="product-regal-option-group__label">{{ __('Available Variants') }}:</label>
                                <div class="product-regal-option-group__pills">
                                    <template x-for="(variant, index) in variants" :key="variant.id">
                                        <button
                                            type="button"
                                            class="product-regal-pill"
                                            :class="selectedVariant === index ? 'is-active' : ''"
                                            @click="selectVariant(index)"
                                        >
                                            <span x-text="Object.values(variant.attributes || {}).join(' / ') || `Variant #${index + 1}`"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div class="product-regal-qty-row">
                            <span class="product-regal-qty-label">{{ __('Quantity :') }}</span>
                            <div class="product-regal-qty-box">
                                <button type="button" class="product-regal-qty-btn" @click="if (quantity > 1) quantity--" :disabled="quantity <= 1">
                                    -
                                </button>
                                <input type="number" min="1" max="99" class="product-regal-qty-input" x-model.number="quantity">
                                <button type="button" class="product-regal-qty-btn" @click="quantity++">
                                    +
                                </button>
                            </div>
                            <span class="product-regal-qty-total" x-show="quantity > 1">
                                {{ __('Total:') }} <strong x-text="money(totalPrice())"></strong>
                            </span>
                        </div>

                        <div class="product-regal-actions">
                            @if ($whatsappPhone)
                                <a
                                    class="product-regal-btn product-regal-btn--primary"
                                    :href="`https://wa.me/{{ $whatsappPhone }}?text=${encodeURIComponent('Hello! I would like to order ' + quantity + 'x ' + productName + (selectedAttributes() ? ' (' + selectedAttributes() + ')' : '') + ' (Product Code: ' + currentSku() + ') for total ' + money(totalPrice()) + '. Link: ' + window.location.href)}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <i class="ph ph-whatsapp-logo"></i>
                                    <span>{{ __('Order on WhatsApp') }}</span>
                                </a>
                            @else
                                <button
                                    type="button"
                                    class="product-regal-btn product-regal-btn--disabled"
                                    disabled
                                >
                                    <i class="ph ph-whatsapp-logo"></i>
                                    <span>{{ __('WhatsApp unavailable') }}</span>
                                </button>
                            @endif
                        </div>

                        <div class="product-regal-share">
                            <button type="button" class="product-regal-share-btn" @click="copyLink">
                                <i class="ph" :class="copied ? 'ph-check text-emerald-600' : 'ph-share-network'"></i>
                                <span x-text="copied ? '{{ __('Link Copied to Clipboard!') }}' : '{{ __('Share Product') }}'"></span>
                            </button>
                            <a href="{{ route('commerce.products.index', $workspace->slug) }}" class="product-regal-store-link">
                                <i class="ph ph-storefront"></i>
                                <span>{{ $workspace->name }}</span>
                            </a>
                        </div>

                        <div class="product-regal-card">
                            <div class="product-regal-card__item product-regal-card__item--stock">
                                <div class="product-regal-card__icon text-emerald-600">
                                    <i class="ph ph-check-circle"></i>
                                </div>
                                <div class="product-regal-card__content">
                                    <h3 class="text-emerald-600 font-bold" x-text="(variants[selectedVariant]?.stock || 0) > 0 ? '{{ __('In Stock') }}' : '{{ __('Check Availability') }}'">
                                        {{ __('In Stock') }}
                                    </h3>
                                    <p x-show="(variants[selectedVariant]?.stock || 0) > 0">
                                        <span x-text="variants[selectedVariant]?.stock || 0"></span> {{ __('available') }}
                                    </p>
                                </div>
                            </div>
                            @if ($whatsappPhone)
                                <div class="product-regal-card__item">
                                    <div class="product-regal-card__icon text-emerald-600">
                                        <i class="ph ph-whatsapp-logo"></i>
                                    </div>
                                    <div class="product-regal-card__content">
                                        <h3>{{ __('WhatsApp ordering') }}</h3>
                                        <p>{{ __('Ask the merchant about delivery, payment, and availability before ordering.') }}</p>
                                    </div>
                                </div>
                            @endif
                            <div class="product-regal-card__item">
                                <div class="product-regal-card__icon text-gray-700">
                                    <i class="ph ph-storefront"></i>
                                </div>
                                <div class="product-regal-card__content">
                                    <h3>{{ $workspace->name }}</h3>
                                    <p>{{ __('Merchant storefront') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Product Content Tabs & Specifications Section -->
        <section class="product-regal-details-section">
            <div class="section-container">
                <div class="product-regal-tabs">
                    <nav class="product-regal-tabs__nav" aria-label="{{ __('Product details tabs') }}">
                        <button
                            type="button"
                            class="product-regal-tab-btn"
                            :class="activeTab === 'details' ? 'is-active' : ''"
                            @click="activeTab = 'details'"
                        >
                            {{ __('Details') }}
                        </button>
                        <button
                            type="button"
                            class="product-regal-tab-btn"
                            :class="activeTab === 'specs' ? 'is-active' : ''"
                            @click="activeTab = 'specs'"
                        >
                            {{ __('Specifications') }}
                        </button>
                        @if ($product->care_information)
                            <button
                                type="button"
                                class="product-regal-tab-btn"
                                :class="activeTab === 'care' ? 'is-active' : ''"
                                @click="activeTab = 'care'"
                            >
                                {{ __('Care Information') }}
                            </button>
                        @endif
                    </nav>

                    <div class="product-regal-tabs__content">
                        <!-- Tab 1: Description Details -->
                        <div x-show="activeTab === 'details'" class="product-regal-tab-panel" x-cloak>
                            <div class="product-regal-description">
                                <p>{{ $product->description ?: __('No detailed product description provided.') }}</p>
                            </div>
                        </div>

                        <!-- Tab 2: Specifications Table -->
                        <div x-show="activeTab === 'specs'" class="product-regal-tab-panel" x-cloak>
                            <table class="product-regal-specs-table">
                                <tbody>
                                    <tr>
                                        <th>{{ __('Product Name') }}</th>
                                        <td>{{ $product->name }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Product Code') }}</th>
                                        <td x-text="currentSku()">{{ $productCode }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Store / Merchant') }}</th>
                                        <td><a href="{{ route('commerce.products.index', $workspace->slug) }}">{{ $workspace->name }}</a></td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Category') }}</th>
                                        <td>{{ $categoryName }}</td>
                                    </tr>
                                    @if ($brandName)
                                        <tr>
                                            <th>{{ __('Brand') }}</th>
                                            <td>{{ $brandName }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->condition)
                                        <tr>
                                            <th>{{ __('Condition') }}</th>
                                            <td>{{ str($product->condition)->replace('_', ' ')->title() }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->country_of_origin)
                                        <tr>
                                            <th>{{ __('Origin') }}</th>
                                            <td>{{ $product->country_of_origin }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        @if ($product->care_information)
                            <div x-show="activeTab === 'care'" class="product-regal-tab-panel" x-cloak>
                                <div class="product-regal-care-box">
                                    <p>{{ $product->care_information }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Related Products Section -->
                @if ($relatedProducts->isNotEmpty())
                    <div class="product-regal-related">
                        <div class="product-regal-related__header">
                            <h2>{{ __('Similar Products from :shop', ['shop' => $workspace->name]) }}</h2>
                            <a href="{{ route('commerce.products.index', $workspace->slug) }}">{{ __('See All') }} <i class="ph ph-arrow-right"></i></a>
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
                                                <strong>{{ $relPrice !== null ? $currencySymbol.' '.number_format($relPrice, 0) : __('Price on request') }}</strong>
                                            </div>
                                            <a href="{{ $relWaLink }}" @if ($whatsappPhone) target="_blank" rel="noopener noreferrer" @endif class="shop-card__whatsapp-btn">
                                                <i class="ph ph-whatsapp-logo"></i>
                                                <span>{{ __('Send WhatsApp') }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </section>
    </article>
@endsection
