@extends($layoutView)

@php
    $gallery = $product->gallery
        ->map(fn ($item) => [
            'id' => $item->media_id,
            'color_id' => $item->color_id,
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

    $colors = $product->colors
        ->map(fn ($color) => [
            'id' => $color->id,
            'name' => $color->name ?? '',
            'hex_code' => $color->hex_code ?: '#2563EB',
            'color_family' => $color->color_family ?? '',
            'swatch_media_id' => $color->swatch_media_id,
            'swatch_image_url' => $color->swatchMedia?->url,
            'display_name' => $color->display_name,
        ])
        ->values();

    $tierPrices = $product->tierPrices
        ->sortBy('min_quantity')
        ->map(fn ($tier) => [
            'min_quantity' => (int) $tier->min_quantity,
            'max_quantity' => $tier->max_quantity ? (int) $tier->max_quantity : null,
            'unit_price' => (float) $tier->unit_price,
            'discount_percentage' => $tier->discount_percentage ? (float) $tier->discount_percentage : null,
        ])
        ->values();

    $variants = $product->variants
        ->map(fn ($variant) => [
            'id' => $variant->id,
            'sku' => $variant->sku ?: ('PRD-'.str_pad((string) $variant->id, 6, '0', STR_PAD_LEFT)),
            'color_id' => $variant->color_id,
            'size' => $variant->size ?? ($variant->attributes['size'] ?? ''),
            'attributes' => $variant->attributes ?? [],
            'price' => (float) $variant->price,
            'compare_at_price' => $variant->compare_at_price ? (float) $variant->compare_at_price : null,
            'stock' => (int) $variant->stock_quantity,
            'weight_kg' => (float) ($variant->weight_kg ?? $product->default_unit_weight_kg ?? 0.030),
            'status' => $variant->status,
            'media_id' => $variant->media_id,
        ])
        ->values();

    $firstVariant = $variants->first();
    $singlePiecePrice = $product->single_piece_price !== null ? (float) $product->single_piece_price : ($firstVariant['price'] ?? 9.00);
    $wholesalePrice = $product->wholesale_price !== null ? (float) $product->wholesale_price : round($singlePiecePrice * 0.72, 2);
    $unitWeightKg = (float) ($product->default_unit_weight_kg ?: 0.030);
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

    // Size list extracted from options or variants
    $sizeOption = $options->first(fn ($o) => strtolower($o['code'] ?? '') === 'size' || strtolower($o['name'] ?? '') === 'size');
    $availableSizes = $sizeOption ? $sizeOption['values'] : ($variants->pluck('size')->filter()->unique()->values()->all() ?: ['S', 'M', 'L', 'XL']);
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
            colors: @js($colors),
            sizes: @js($availableSizes),
            productName: @js($product->name),
            currencySymbol: @js($currencySymbol),
            whatsappPhone: @js($whatsappPhone),
            singlePiecePrice: @js($singlePiecePrice),
            wholesalePrice: @js($wholesalePrice),
            basePrice: @js($singlePiecePrice),
            unitWeightKg: @js($unitWeightKg),
            baseShippingRatePerKg: 50.00,
            minShippingKg: 1.0,
            activeMedia: 0,
            selectedVariant: 0,
            selectedColorIndex: 0,
            selectedOptions: {},
            buyMode: 'wholesale', // 'single' or 'wholesale'
            singleQty: 1,
            matrix: {},
            zoomOpen: false,
            isHovering: false,
            copied: false,
            zoomX: 50,
            zoomY: 50,
            activeTab: 'details',

            init() {
                // Initialize option selection
                if (this.options && this.options.length > 0) {
                    const firstAttrs = this.variants[0]?.attributes || {};
                    this.options.forEach(opt => {
                        this.selectedOptions[opt.code] = firstAttrs[opt.code] || (opt.values && opt.values[0]) || '';
                    });
                }

                // Initialize matrix with zero quantities for each color and size
                const colorList = this.colors.length > 0 ? this.colors : [{ display_name: 'Standard Color', hex_code: '#2563EB' }];
                colorList.forEach((c, cIdx) => {
                    const cKey = c.display_name || `Color #${cIdx + 1}`;
                    this.matrix[cKey] = {};
                    this.sizes.forEach(s => {
                        this.matrix[cKey][s] = 0;
                    });
                });
            },

            handleMouseMove(e) {
                const rect = e.currentTarget.getBoundingClientRect();
                this.zoomX = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100));
                this.zoomY = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100));
            },

            selectColor(index) {
                this.selectedColorIndex = index;
                const colorObj = this.colors[index];
                if (colorObj) {
                    this.selectedOptions['color'] = colorObj.name || colorObj.hex_code;
                    
                    // First look for photos explicitly assigned to this color
                    let mediaIndex = -1;
                    if (colorObj.id) {
                        mediaIndex = this.gallery.findIndex(item => item.color_id && String(item.color_id) === String(colorObj.id));
                    }
                    if (mediaIndex < 0 && colorObj.swatch_media_id) {
                        mediaIndex = this.gallery.findIndex(item => String(item.id) === String(colorObj.swatch_media_id));
                    }
                    if (mediaIndex >= 0) {
                        this.activeMedia = mediaIndex;
                        this.scrollThumbIntoView(mediaIndex);
                    }

                    const matchIndex = this.variants.findIndex(v => {
                        return (v.color_id && v.color_id === colorObj.id) || 
                               (v.attributes?.color && String(v.attributes.color).toLowerCase() === String(colorObj.name || colorObj.hex_code).toLowerCase());
                    });
                    if (matchIndex >= 0) {
                        this.selectVariant(matchIndex);
                    }
                }
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
                const num = Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return `${this.currencySymbol} ${num}`;
            },

            totalPieces() {
                if (this.buyMode === 'single') {
                    return Math.max(1, parseInt(this.singleQty || 1, 10));
                }
                let total = 0;
                Object.values(this.matrix).forEach(sizeObj => {
                    Object.values(sizeObj).forEach(q => {
                        total += Math.max(0, parseInt(q || 0, 10));
                    });
                });
                return total > 0 ? total : 100; // default 100 wholesale demo if empty
            },

            unitPrice() {
                if (this.buyMode === 'single') {
                    return this.singlePiecePrice;
                }
                return this.wholesalePrice;
            },

            garmentSubtotal() {
                return this.totalPieces() * this.unitPrice();
            },

            totalWeightKg() {
                return Math.max(0.001, (this.totalPieces() * this.unitWeightKg)).toFixed(2);
            },

            chargeableWeightKg() {
                return Math.max(this.minShippingKg, Math.ceil(this.totalWeightKg()));
            },

            shippingCost() {
                return this.chargeableWeightKg() * this.baseShippingRatePerKg;
            },

            totalLandedCost() {
                return this.garmentSubtotal() + this.shippingCost();
            },

            effectivePricePerUnit() {
                return (this.totalLandedCost() / this.totalPieces()).toFixed(2);
            },

            singlePieceLandedCost() {
                return (this.singlePiecePrice + (this.minShippingKg * this.baseShippingRatePerKg)).toFixed(2);
            },

            savingsPerUnit() {
                const diff = this.singlePieceLandedCost() - this.effectivePricePerUnit();
                return Math.max(0, diff).toFixed(2);
            },

            totalSavings() {
                return (this.savingsPerUnit() * this.totalPieces()).toFixed(2);
            },

            buildWhatsAppText() {
                let text = `*👗 Order / Quote Inquiry — ${this.productName}*\n`;
                text += `━━━━━━━━━━━━━━━━━━━━\n`;
                text += `• Total Quantity: *${this.totalPieces()} pcs*\n`;
                text += `• Unit Tier Price: *${this.money(this.unitPrice())}/pc*\n`;
                text += `• Garment Subtotal: *${this.money(this.garmentSubtotal())}*\n`;
                text += `• Est. Weight: *${this.totalWeightKg()} kg*\n`;
                text += `• Est. Shipping: *${this.money(this.shippingCost())}*\n`;
                text += `━━━━━━━━━━━━━━━━━━━━\n`;
                text += `💰 *Total Landed: ${this.money(this.totalLandedCost())}*\n`;
                text += `💡 *Effective Cost Per Piece: ${this.money(this.effectivePricePerUnit())}/pc*\n`;

                if (this.buyMode === 'wholesale') {
                    text += `\n*Selected Breakdown:*\n`;
                    Object.entries(this.matrix).forEach(([colorName, sizes]) => {
                        const sizeParts = [];
                        Object.entries(sizes).forEach(([size, qty]) => {
                            if (parseInt(qty, 10) > 0) sizeParts.push(`${size}: ${qty} pcs`);
                        });
                        if (sizeParts.length > 0) {
                            text += `• ${colorName}: ${sizeParts.join(', ')}\n`;
                        }
                    });
                } else {
                    const selAttrs = Object.entries(this.selectedOptions).map(([k, v]) => `${k}: ${v}`).join(', ');
                    if (selAttrs) text += `• Selected: ${selAttrs}\n`;
                }

                text += `\n🔗 Link: ${window.location.href}`;
                return text;
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
        <!-- Fullscreen Lightbox Modal -->
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
                                    </div>
                                </template>
                            </div>

                            <button type="button" class="product-regal-thumbs__nav product-regal-thumbs__nav--next" @click="nextMedia" aria-label="{{ __('Next thumbnail') }}">
                                <i class="ph ph-caret-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Product Details, Color Swatches & Wholesale Calculator -->
                    <div class="product-regal-info space-y-6">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge bg-primary/10 text-primary font-bold text-xs px-2.5 py-1 rounded-full">
                                    {{ __('Garment Direct Export') }}
                                </span>
                                @if($product->fabric_gsm)
                                    <span class="badge bg-neutral-100 text-neutral-800 font-semibold text-xs px-2.5 py-1 rounded-full">
                                        <i class="ph ph-tag"></i> {{ $product->fabric_gsm }}
                                    </span>
                                @endif
                                @if($product->material)
                                    <span class="badge bg-neutral-100 text-neutral-700 text-xs px-2.5 py-1 rounded-full">
                                        {{ $product->material }}
                                    </span>
                                @endif
                            </div>

                            <h1 id="product-page-heading" class="product-regal-info__title mt-2">{{ $product->name }}</h1>

                            <div class="product-regal-info__meta flex flex-wrap items-center gap-4 text-xs text-neutral-500 mt-1">
                                <span>{{ __('SKU:') }} <strong class="text-neutral-800" x-text="variants[selectedVariant]?.sku || @js($productCode)">{{ $productCode }}</strong></span>
                                <span>{{ __('Weight:') }} <strong class="text-neutral-800" x-text="`${unitWeightKg} kg/piece`">{{ $unitWeightKg }} kg/pc</strong></span>
                                <span>{{ __('Origin:') }} <strong class="text-neutral-800">{{ $product->country_of_origin ?? 'Bangladesh' }}</strong></span>
                            </div>
                        </div>

                        {{-- Two-Price Comparison Banner --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl border p-4 bg-neutral-50/70 transition-all" :class="buyMode === 'single' ? 'border-primary ring-2 ring-primary/20 bg-white' : 'border-neutral-200'">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-500">{{ __('Single Sample Buy') }}</span>
                                    <span class="badge badge-soft text-[10px]">{{ __('From 1 pc') }}</span>
                                </div>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <span class="text-2xl font-black text-neutral-900" x-text="money(singlePiecePrice)">{{ $currencySymbol }} {{ number_format($singlePiecePrice, 2) }}</span>
                                    <span class="text-xs text-neutral-500 font-medium">/ pc</span>
                                </div>
                                <p class="text-[11px] text-neutral-500 mt-0.5">{{ __('Direct sample order, no minimum') }}</p>
                            </div>

                            <div class="rounded-2xl border p-4 bg-emerald-50/50 border-emerald-500/30 transition-all" :class="buyMode === 'wholesale' ? 'border-emerald-500 ring-2 ring-emerald-500/20 bg-emerald-50/80' : 'border-neutral-200'">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">{{ __('Wholesale Bulk Price') }}</span>
                                    <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white">{{ __('Export Rate') }}</span>
                                </div>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <span class="text-2xl font-black text-emerald-700" x-text="money(wholesalePrice)">{{ $currencySymbol }} {{ number_format($wholesalePrice, 2) }}</span>
                                    <span class="text-xs text-emerald-600 font-medium">/ pc</span>
                                </div>
                                <p class="text-[11px] text-emerald-700 mt-0.5">{{ __('Wholesale matrix order') }}</p>
                            </div>
                        </div>

                        {{-- Visual Color Swatches Selector --}}
                        @if ($colors->isNotEmpty())
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-neutral-600 mb-2">
                                    {{ __('Garment Color Swatches') }}: <strong class="text-neutral-900" x-text="colors[selectedColorIndex]?.display_name || 'Selected'"></strong>
                                </label>
                                <div class="flex flex-wrap items-center gap-3">
                                    <template x-for="(color, cIdx) in colors" :key="cIdx">
                                        <button
                                            type="button"
                                            class="group relative flex items-center gap-2 rounded-full border px-3 py-1.5 transition-all text-xs font-medium"
                                            :class="selectedColorIndex === cIdx ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary/30' : 'border-neutral-200 hover:border-neutral-300 bg-white text-neutral-700'"
                                            @click="selectColor(cIdx)"
                                            :title="color.display_name"
                                        >
                                            <span class="h-4 w-4 rounded-full border border-black/10 shadow-xs shrink-0" :style="`background-color: ${color.hex_code}`"></span>
                                            <span x-text="color.display_name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        @endif

                        {{-- Mode Selector Tabs: Single / Sample (1 pc) vs Wholesale Bulk Matrix --}}
                        <div>
                            <div class="flex items-center gap-2 border-b border-neutral-200 pb-1 mb-4">
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-bold border-b-2 transition-all"
                                    :class="buyMode === 'wholesale' ? 'border-primary text-primary' : 'border-transparent text-neutral-500 hover:text-neutral-700'"
                                    @click="buyMode = 'wholesale'"
                                >
                                    <i class="ph ph-grid-nine"></i> {{ __('Wholesale Bulk Matrix (10+ pcs)') }}
                                </button>
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-bold border-b-2 transition-all"
                                    :class="buyMode === 'single' ? 'border-primary text-primary' : 'border-transparent text-neutral-500 hover:text-neutral-700'"
                                    @click="buyMode = 'single'"
                                >
                                    <i class="ph ph-shopping-bag"></i> {{ __('Single / Sample Piece (1 pc)') }}
                                </button>
                            </div>

                            {{-- Single Piece Buying Controls --}}
                            <div x-show="buyMode === 'single'" class="space-y-4">
                                @if (count($availableSizes) > 0)
                                    <div>
                                        <label class="block text-xs font-semibold text-neutral-600 mb-1.5">{{ __('Select Size') }}:</label>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="sz in sizes" :key="sz">
                                                <button
                                                    type="button"
                                                    class="min-w-[44px] h-10 px-3 rounded-xl border text-sm font-semibold transition-all"
                                                    :class="selectedOptions['size'] === sz ? 'border-primary bg-primary text-white shadow-xs' : 'border-neutral-200 bg-white text-neutral-800 hover:border-neutral-300'"
                                                    @click="selectOption('size', sz)"
                                                    x-text="sz"
                                                ></button>
                                            </template>
                                        </div>
                                    </div>
                                @endif

                                <div class="flex items-center gap-4">
                                    <span class="text-xs font-semibold text-neutral-600">{{ __('Quantity (Pieces):') }}</span>
                                    <div class="flex items-center rounded-xl border border-neutral-300 bg-white">
                                        <button type="button" class="w-10 h-10 flex items-center justify-center text-lg hover:bg-neutral-50" @click="if (singleQty > 1) singleQty--" :disabled="singleQty <= 1">-</button>
                                        <input type="number" min="1" max="999" class="w-14 text-center font-bold border-0 focus:ring-0 p-0 text-neutral-800" x-model.number="singleQty">
                                        <button type="button" class="w-10 h-10 flex items-center justify-center text-lg hover:bg-neutral-50" @click="singleQty++">+</button>
                                    </div>
                                </div>
                            </div>

                            {{-- Wholesale Matrix Table --}}
                            <div x-show="buyMode === 'wholesale'" class="overflow-x-auto rounded-2xl border border-neutral-200 bg-neutral-50/50 p-3">
                                <p class="text-xs text-neutral-600 mb-2 font-medium">
                                    <i class="ph ph-info"></i> {{ __('Enter desired quantity per color and size. The total wholesale price & shipping calculates automatically below.') }}
                                </p>
                                <table class="w-full text-xs text-left border-collapse bg-white rounded-xl overflow-hidden shadow-xs">
                                    <thead>
                                        <tr class="border-b bg-neutral-100/70 text-neutral-700 font-semibold">
                                            <th class="p-2.5">{{ __('Colorway') }}</th>
                                            <template x-for="sz in sizes" :key="sz">
                                                <th class="p-2.5 text-center" x-text="sz"></th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-neutral-100">
                                        <template x-for="(c, cIdx) in (colors.length > 0 ? colors : [{ display_name: 'Standard Color', hex_code: '#2563EB' }])" :key="cIdx">
                                            <tr>
                                                <td class="p-2.5 font-medium flex items-center gap-2">
                                                    <span class="h-3.5 w-3.5 rounded-full border border-black/10 shadow-xs shrink-0" :style="`background-color: ${c.hex_code}`"></span>
                                                    <span x-text="c.display_name"></span>
                                                </td>
                                                <template x-for="sz in sizes" :key="sz">
                                                    <td class="p-1.5 text-center">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            class="w-14 rounded-lg border border-neutral-300 p-1.5 text-center font-bold focus:border-primary focus:ring-1 focus:ring-primary text-neutral-800"
                                                            placeholder="0"
                                                            x-model.number="matrix[c.display_name || `Color #${cIdx+1}`][sz]"
                                                        >
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Dynamic Landed Cost & Shipping Breakdown Card --}}
                        <div class="rounded-2xl border border-neutral-200 bg-gradient-to-br from-neutral-50 to-neutral-100 p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between border-b border-neutral-200/80 pb-3">
                                <div>
                                    <span class="text-xs uppercase font-bold tracking-wider text-neutral-500">{{ __('Live Landed Cost Summary') }}</span>
                                    <h4 class="text-base font-extrabold text-neutral-900" x-text="`${totalPieces()} pieces total`">100 pieces</h4>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-neutral-500">{{ __('Unit Price Tier') }}</span>
                                    <p class="text-base font-extrabold text-emerald-600" x-text="`${money(unitPrice())} / piece`"></p>
                                </div>
                            </div>

                            <dl class="space-y-2 text-xs text-neutral-700">
                                <div class="flex justify-between">
                                    <dt class="text-neutral-500">{{ __('Garment Pieces Subtotal:') }}</dt>
                                    <dd class="font-bold text-neutral-900" x-text="money(garmentSubtotal())"></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-neutral-500">{{ __('Estimated Total Weight:') }}</dt>
                                    <dd class="font-bold text-neutral-900" x-text="`${totalWeightKg()} kg (${chargeableWeightKg()} kg chargeable)`"></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-neutral-500">{{ __('International Freight & Shipping:') }}</dt>
                                    <dd class="font-bold text-neutral-900" x-text="money(shippingCost())"></dd>
                                </div>
                                <div class="border-t border-neutral-200 pt-2 flex justify-between text-sm">
                                    <dt class="font-extrabold text-neutral-900">{{ __('Total Landed Cost (Garments + Shipping):') }}</dt>
                                    <dd class="font-black text-primary text-base" x-text="money(totalLandedCost())"></dd>
                                </div>
                                <div class="flex justify-between text-xs bg-emerald-100/60 p-2.5 rounded-xl border border-emerald-200 text-emerald-800">
                                    <dt class="font-bold flex items-center gap-1.5">
                                        <i class="ph ph-sparkle text-emerald-600 text-sm"></i>
                                        {{ __('Effective Cost Per Piece (Landed):') }}
                                    </dt>
                                    <dd class="font-extrabold text-sm" x-text="`${money(effectivePricePerUnit())} / pc`"></dd>
                                </div>
                            </dl>

                            {{-- Wholesale Savings Callout Banner --}}
                            <template x-if="totalPieces() >= 10 && savingsPerUnit() > 0">
                                <div class="flex items-center gap-2 rounded-xl bg-emerald-500 text-white p-3 text-xs font-semibold shadow-xs">
                                    <i class="ph ph-tag-chevron text-lg"></i>
                                    <span>
                                        {{ __('Wholesale Advantage: You save :savings / pc (:total total) compared to single sample buy!', ['savings' => '${money(savingsPerUnit())}', 'total' => '${money(totalSavings())}']) }}
                                    </span>
                                </div>
                            </template>

                            {{-- One-Click WhatsApp Action Button --}}
                            <div class="pt-2">
                                @if ($whatsappPhone)
                                    <a
                                        :href="`https://wa.me/{{ $whatsappPhone }}?text=${encodeURIComponent(buildWhatsAppText())}`"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold py-3.5 px-4 shadow-md transition-all text-sm"
                                    >
                                        <i class="ph ph-whatsapp-logo text-xl"></i>
                                        <span>{{ __('Order on WhatsApp') }}</span>
                                    </a>
                                @else
                                    <button type="button" class="w-full py-3.5 px-4 rounded-xl bg-neutral-300 text-neutral-600 font-bold text-sm cursor-not-allowed" disabled>
                                        <i class="ph ph-whatsapp-logo"></i> {{ __('WhatsApp Ordering Unavailable') }}
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="product-regal-share flex items-center justify-between gap-4 pt-2 border-t border-neutral-200">
                            <button type="button" class="product-regal-share-btn flex items-center gap-1.5 text-xs text-neutral-600 hover:text-neutral-900" @click="copyLink">
                                <i class="ph" :class="copied ? 'ph-check text-emerald-600' : 'ph-share-network'"></i>
                                <span x-text="copied ? '{{ __('Link Copied to Clipboard!') }}' : '{{ __('Share Product') }}'"></span>
                            </button>
                            <a href="{{ route('commerce.products.index', $workspace->slug) }}" class="product-regal-store-link flex items-center gap-1.5 text-xs text-primary font-semibold hover:underline">
                                <i class="ph ph-storefront"></i>
                                <span>{{ $workspace->name }}</span>
                            </a>
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
                            {{ __('Product Details') }}
                        </button>
                        <button
                            type="button"
                            class="product-regal-tab-btn"
                            :class="activeTab === 'specs' ? 'is-active' : ''"
                            @click="activeTab = 'specs'"
                        >
                            {{ __('Garment Specifications') }}
                        </button>
                        @if ($product->care_information)
                            <button
                                type="button"
                                class="product-regal-tab-btn"
                                :class="activeTab === 'care' ? 'is-active' : ''"
                                @click="activeTab = 'care'"
                            >
                                {{ __('Care & Washing') }}
                            </button>
                        @endif
                    </nav>

                    <div class="product-regal-tabs__content">
                        <!-- Tab 1: Description Details -->
                        <div x-show="activeTab === 'details'" class="product-regal-tab-panel" x-cloak>
                            <div class="product-regal-description prose max-w-none text-neutral-700 leading-relaxed">
                                <p>{{ $product->description ?: __('High-quality garment manufacturing directly from verified exporters. Suitable for both single unit samples and bulk container orders.') }}</p>
                            </div>
                        </div>

                        <!-- Tab 2: Specifications Table -->
                        <div x-show="activeTab === 'specs'" class="product-regal-tab-panel" x-cloak>
                            <table class="product-regal-specs-table w-full text-sm">
                                <tbody>
                                    <tr>
                                        <th class="py-2 text-neutral-500 font-medium w-1/3">{{ __('Product Name') }}</th>
                                        <td class="py-2 text-neutral-900 font-semibold">{{ $product->name }}</td>
                                    </tr>
                                    @if ($product->fabric_gsm)
                                        <tr>
                                            <th class="py-2 text-neutral-500 font-medium">{{ __('Fabric GSM') }}</th>
                                            <td class="py-2 text-neutral-900 font-semibold">{{ $product->fabric_gsm }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->material)
                                        <tr>
                                            <th class="py-2 text-neutral-500 font-medium">{{ __('Material Composition') }}</th>
                                            <td class="py-2 text-neutral-900 font-semibold">{{ $product->material }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th class="py-2 text-neutral-500 font-medium">{{ __('Weight per Piece') }}</th>
                                        <td class="py-2 text-neutral-900 font-semibold">{{ $unitWeightKg }} kg ({{ round($unitWeightKg * 1000) }} grams)</td>
                                    </tr>
                                    <tr>
                                        <th class="py-2 text-neutral-500 font-medium">{{ __('Country of Origin') }}</th>
                                        <td class="py-2 text-neutral-900 font-semibold">{{ $product->country_of_origin ?? 'Bangladesh' }}</td>
                                    </tr>
                                    @if ($brandName)
                                        <tr>
                                            <th class="py-2 text-neutral-500 font-medium">{{ __('Manufacturer / Brand') }}</th>
                                            <td class="py-2 text-neutral-900 font-semibold">{{ $brandName }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th class="py-2 text-neutral-500 font-medium">{{ __('Store / Merchant') }}</th>
                                        <td class="py-2"><a class="text-primary hover:underline font-semibold" href="{{ route('commerce.products.index', $workspace->slug) }}">{{ $workspace->name }}</a></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        @if ($product->care_information)
                            <div x-show="activeTab === 'care'" class="product-regal-tab-panel" x-cloak>
                                <div class="product-regal-care-box p-4 rounded-xl bg-neutral-50 text-neutral-700 text-sm">
                                    <p>{{ $product->care_information }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Related Products Section -->
                @if ($relatedProducts->isNotEmpty())
                    <div class="product-regal-related mt-12">
                        <div class="product-regal-related__header flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-neutral-900">{{ __('More Garments from :shop', ['shop' => $workspace->name]) }}</h2>
                            <a href="{{ route('commerce.products.index', $workspace->slug) }}" class="text-sm font-semibold text-primary hover:underline">{{ __('See All') }} <i class="ph ph-arrow-right"></i></a>
                        </div>

                        <div class="shop-grid">
                            @foreach ($relatedProducts as $relProduct)
                                @php
                                    $relPrice = $relProduct->starting_price !== null ? (float) $relProduct->starting_price : ($relProduct->single_piece_price !== null ? (float) $relProduct->single_piece_price : null);
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
                                                <strong>{{ $relPrice !== null ? $currencySymbol.' '.number_format($relPrice, 2) : __('Price on request') }}</strong>
                                            </div>
                                            <a href="{{ $relWaLink }}" @if ($whatsappPhone) target="_blank" rel="noopener noreferrer" @endif class="shop-card__whatsapp-btn">
                                                <i class="ph ph-whatsapp-logo"></i>
                                                <span>{{ __('Order') }}</span>
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
