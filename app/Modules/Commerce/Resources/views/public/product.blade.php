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
            money(value) {
                return new Intl.NumberFormat('en-US', { style: 'currency', currency: @js($currency) }).format(value || 0);
            },
            selectedAttributes() {
                return Object.values(this.variants[this.selectedVariant]?.attributes || {}).join(' / ');
            }
        }"
    >
        <header class="product-public__hero">
            <div class="section-container">
                <div class="product-public__breadcrumb">
                    <a href="{{ route('commerce.products.index', $workspace->slug) }}">{{ __('Shop') }}</a>
                    <span>{{ $categoryName }}</span>
                </div>
                <div class="product-public__heading">
                    <div>
                        @if ($brandName)
                            <span>{{ $brandName }}</span>
                        @endif
                        <h1 id="product-public-heading">{{ $product->name }}</h1>
                        @if ($product->description)
                            <p>{{ $product->description }}</p>
                        @endif
                    </div>
                    <div class="product-public__price-card">
                        <span>{{ __('Starting at') }}</span>
                        <strong>{{ $startingPrice !== null ? $currency.' '.number_format((float) $startingPrice, 2) : __('Quote') }}</strong>
                        <p>{{ __('Final shipping, duties, and availability are confirmed before payment.') }}</p>
                    </div>
                </div>
            </div>
        </header>

        <div class="product-public__body">
            <div class="section-container">
                <div class="product-public__grid">
                    <section class="product-gallery" aria-label="{{ __('Product media') }}">
                        <div class="product-gallery__stage">
                            <template x-for="(item, index) in gallery" :key="item.id">
                                <div x-show="activeMedia === index" x-cloak>
                                    <template x-if="item.type === 'image'">
                                        <img :src="item.url" :alt="item.alt">
                                    </template>
                                    <template x-if="item.type === 'video'">
                                        <video :src="item.url" controls preload="metadata"></video>
                                    </template>
                                </div>
                            </template>
                            <div class="product-gallery__empty" x-show="gallery.length === 0">
                                <i class="ph ph-t-shirt"></i>
                            </div>
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

                    <aside class="product-buy-box">
                        <div class="product-buy-box__price">
                            <div>
                                <span>{{ __('Selected price') }}</span>
                                <strong x-text="money(variants[selectedVariant]?.price)">{{ $startingPrice !== null ? $currency.' '.number_format((float) $startingPrice, 2) : $currency.' 0.00' }}</strong>
                            </div>
                            <span class="product-stock" :class="(variants[selectedVariant]?.stock || 0) > 0 ? 'is-available' : 'is-limited'" x-text="(variants[selectedVariant]?.stock || 0) > 0 ? '{{ __('In stock') }}' : '{{ __('Check availability') }}'"></span>
                        </div>

                        <div class="product-variants" x-show="variants.length > 0">
                            <h2>{{ __('Choose a variant') }}</h2>
                            <div>
                                <template x-for="(variant, index) in variants" :key="variant.id">
                                    <button type="button" :class="selectedVariant === index ? 'is-active' : ''" @click="selectVariant(index)">
                                        <span>
                                            <template x-for="(value, key) in variant.attributes" :key="key">
                                                <span><span x-text="key + ':'"></span> <strong x-text="value"></strong></span>
                                            </template>
                                        </span>
                                        <strong x-text="money(variant.price)"></strong>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="product-info-panel">
                            <i class="ph ph-package"></i>
                            <div>
                                <strong>{{ __('Shipping from Bangladesh') }}</strong>
                                <p>{{ __('Shipping cost, duties, delivery time, and final availability are manually confirmed in WhatsApp before payment.') }}</p>
                            </div>
                        </div>

                        @if ($whatsappPhone)
                            <a
                                class="product-whatsapp"
                                :href="`https://wa.me/{{ $whatsappPhone }}?text=${encodeURIComponent('Hello, I am interested in ' + productName + (selectedAttributes() ? ' (' + selectedAttributes() + ')' : '') + '.')}`"
                                target="_blank"
                                rel="noopener"
                            >
                                <i class="ph ph-whatsapp-logo"></i>
                                {{ __('Ask and order on WhatsApp') }}
                            </a>
                        @endif

                        <dl class="product-facts">
                            <div>
                                <dt>{{ __('Category') }}</dt>
                                <dd>{{ $categoryName }}</dd>
                            </div>
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
                            <details class="product-care">
                                <summary>{{ __('Care information') }}</summary>
                                <p>{{ $product->care_information }}</p>
                            </details>
                        @endif
                    </aside>
                </div>
            </div>
        </div>
    </article>
@endsection
