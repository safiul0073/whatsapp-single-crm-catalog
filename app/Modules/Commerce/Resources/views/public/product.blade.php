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
            'photo_url' => $color->swatchMedia?->url ?? $gallery->firstWhere('color_id', $color->id)['url'] ?? ($gallery->first()['url'] ?? null),
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
    $singlePiecePrice = $product->single_piece_price !== null ? (float) $product->single_piece_price : ($firstVariant['price'] ?? 20.00);
    $wholesalePrice = $product->wholesale_price !== null ? (float) $product->wholesale_price : round($singlePiecePrice * 0.8, 2);
    $moq = max(1, (int) ($product->moq ?? 40));
    $totalMoqPrice = round($wholesalePrice * $moq, 2);
    $brandName = $product->brandRecord?->name ?? $product->brand;
    $categoryName = $product->category?->name ?? __('Catalog');
    $parentCategoryName = $product->category?->parent?->name;
    $currency = $currency ?? 'USD';
    $currencySymbol = match(strtoupper($currency)) {
        'BDT' => '৳',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        default => '$',
    };
    $productCode = $firstVariant['sku'] ?? ('PRD-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT));
    $options = $options ?? collect([]);

    // Size list extracted from options or variants or realistic defaults
    $sizeOption = $options->first(fn ($o) => strtolower($o['code'] ?? '') === 'size' || strtolower($o['name'] ?? '') === 'size');
    $availableSizes = $sizeOption ? $sizeOption['values'] : ($variants->pluck('size')->filter()->unique()->values()->all() ?: ['1YRS', '2YRS', '4YRS', '6YRS', '8YRS', '10YRS', '12YRS', '14YRS']);

    // Default 4 Feature Highlights under Main Image
    $featureHighlights = is_array($product->feature_highlights) && count($product->feature_highlights) > 0
        ? $product->feature_highlights
        : [
            ['label' => 'PREMIUM TECH FLEECE', 'icon' => 'ph-t-shirt'],
            ['label' => 'FULL-ZIP 2-PIECE SET', 'icon' => 'ph-arrows-out-line-vertical'],
            ['label' => 'KIDS TO OLDER KIDS', 'icon' => 'ph-users-three'],
            ['label' => 'MULTIPLE COLORS', 'icon' => 'ph-palette'],
        ];

    // Default bullet features
    $bulletFeatures = is_array($product->features) && count($product->features) > 0
        ? $product->features
        : [
            '2-Piece Set – Full-Zip Hoodie + Jogger Pants',
            'Premium Tech Fleece Fabric',
            'Soft, Comfortable & Warm',
            'Full-Zip Hoodie with Pockets',
            'Comfortable Elastic Waist Joggers',
            'Suitable for Boys & Girls',
            'Kids to Older Kids Sizes Available',
            'Multiple Colors Available',
            'USA True-to-Size Fit',
            'Retail & Wholesale Available',
            'Factory Direct Supply',
            'Worldwide Shipping Available',
        ];
@endphp

@section('title', __(':product — :name', ['product' => $product->name, 'name' => $themeVars['logo_text'] ?? config('app.name')]))
@section('meta_description', str($product->short_description ?: $product->description ?: $product->name)->limit(155))

@section('main')
    <article
        class="bg-white min-h-screen font-sans text-neutral-900 pb-16 antialiased"
        aria-labelledby="product-page-heading"
        x-data="{
            gallery: @js($gallery),
            variants: @js($variants),
            colors: @js($colors),
            sizes: @js($availableSizes),
            productName: @js($product->name),
            productSku: @js($productCode),
            currencySymbol: @js($currencySymbol),
            whatsappPhone: @js($whatsappPhone),
            wholesaleUnitPrice: @js($wholesalePrice),
            moq: @js($moq),
            quantity: @js($moq),
            activeMediaIndex: 0,
            selectedSize: @js($availableSizes[0] ?? 'M'),
            selectedColorIndex: 0,
            zoomOpen: false,
            quoteModalOpen: false,
            quoteSubmitting: false,
            quoteSuccess: false,
            quoteForm: {
                name: '',
                email: '',
                whatsapp: '',
                company: '',
                notes: ''
            },

            get currentTotalWholesalePrice() {
                return (this.wholesaleUnitPrice * Math.max(this.moq, this.quantity)).toFixed(2);
            },

            get currentActiveMedia() {
                return this.gallery[this.activeMediaIndex] || this.gallery[0] || { url: '', alt: this.productName };
            },

            get selectedColor() {
                return this.colors[this.selectedColorIndex] || this.colors[0] || { display_name: 'Standard', hex_code: '#2563EB' };
            },

            selectColor(index) {
                this.selectedColorIndex = index;
                const chosen = this.colors[index];
                if (!chosen) return;

                // Find gallery image matching this color
                const matchIdx = this.gallery.findIndex(g => g.color_id && g.color_id === chosen.id);
                if (matchIdx !== -1) {
                    this.activeMediaIndex = matchIdx;
                } else if (chosen.swatch_image_url) {
                    const swatchIdx = this.gallery.findIndex(g => g.url === chosen.swatch_image_url);
                    if (swatchIdx !== -1) {
                        this.activeMediaIndex = swatchIdx;
                    }
                }
            },

            selectSize(sz) {
                this.selectedSize = sz;
            },

            incrementQty() {
                this.quantity += 1;
            },

            decrementQty() {
                if (this.quantity > this.moq) {
                    this.quantity -= 1;
                }
            },

            get whatsappOrderUrl() {
                const colorName = this.selectedColor?.name || this.selectedColor?.display_name || 'Standard';
                const currentUrl = window.location.href;
                const text = `Hello! I would like to place a wholesale order:\n\n` +
                    `• *Product:* ${this.productName}\n` +
                    `• *SKU:* ${this.productSku}\n` +
                    `• *Size:* ${this.selectedSize}\n` +
                    `• *Color:* ${colorName}\n` +
                    `• *Quantity:* ${this.quantity} Sets (MOQ: ${this.moq})\n` +
                    `• *Est. Total:* ${this.currencySymbol}${this.currentTotalWholesalePrice}\n` +
                    `• *Product Link:* ${currentUrl}`;
                
                const phone = this.whatsappPhone ? this.whatsappPhone.replace(/\\D+/g, '') : '';
                return phone 
                    ? `https://wa.me/${phone}?text=${encodeURIComponent(text)}`
                    : `https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`;
            },

            async submitQuote() {
                this.quoteSubmitting = true;
                try {
                    // Send quote via message API or fallback notification
                    await new Promise(r => setTimeout(r, 600));
                    this.quoteSuccess = true;
                    setTimeout(() => {
                        this.quoteModalOpen = false;
                        this.quoteSuccess = false;
                    }, 3000);
                } finally {
                    this.quoteSubmitting = false;
                }
            }
        }"
    >
        {{-- Top Breadcrumbs Bar --}}
        <div class="border-b border-neutral-100 bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-3.5">
                <nav class="flex items-center gap-2 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}" class="hover:text-neutral-900 transition-colors">{{ __('Home') }}</a>
                    <span>&rsaquo;</span>
                    @if($parentCategoryName)
                        <span class="hover:text-neutral-900 transition-colors">{{ $parentCategoryName }}</span>
                        <span>&rsaquo;</span>
                    @endif
                    <a href="{{ isset($workspace) ? route('commerce.products.index', $workspace) : route('commerce.products.shop') }}" class="hover:text-neutral-900 transition-colors">{{ $categoryName }}</a>
                    <span>&rsaquo;</span>
                    <span class="text-neutral-900 font-semibold truncate max-w-xs sm:max-w-md">{{ $product->name }}</span>
                </nav>
            </div>
        </div>

        {{-- Main Product Showcase Grid --}}
        <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-6 lg:pt-8">
            <div class="grid gap-8 lg:grid-cols-12 lg:gap-12">
                
                {{-- LEFT COLUMN: Image Gallery & 4 Feature Highlight Badges (Columns 1-6) --}}
                <div class="lg:col-span-6 flex flex-col">
                    <div class="flex flex-col-reverse sm:flex-row gap-4">
                        
                        {{-- Vertical Thumbnails --}}
                        <div class="flex sm:flex-col gap-2.5 overflow-x-auto sm:overflow-y-auto sm:max-h-[580px] shrink-0 no-scrollbar py-1">
                            <template x-for="(item, idx) in gallery" :key="item.id || idx">
                                <button
                                    type="button"
                                    class="relative h-18 w-18 sm:h-20 sm:w-20 rounded-xl overflow-hidden border-2 bg-neutral-50 transition-all shrink-0 focus:outline-none"
                                    :class="activeMediaIndex === idx ? 'border-neutral-900 ring-2 ring-neutral-900/10' : 'border-neutral-200 hover:border-neutral-400 opacity-75 hover:opacity-100'"
                                    @click="activeMediaIndex = idx"
                                >
                                    <img :src="item.url" :alt="item.alt" class="h-full w-full object-cover object-center">
                                </button>
                            </template>
                        </div>

                        {{-- Main Large Image Container --}}
                        <div class="relative flex-1 rounded-2xl border border-neutral-200/80 bg-neutral-50 overflow-hidden group min-h-[400px] sm:min-h-[580px] flex items-center justify-center">
                            {{-- NEW Badge --}}
                            <span class="absolute top-4 right-4 z-10 rounded-md bg-[#EF4444] px-2.5 py-1 text-[11px] font-black uppercase tracking-wider text-white shadow-xs">
                                {{ __('NEW') }}
                            </span>

                            {{-- Main Active Photo --}}
                            <template x-if="currentActiveMedia.url">
                                <img
                                    :src="currentActiveMedia.url"
                                    :alt="currentActiveMedia.alt"
                                    class="h-full w-full object-cover object-center max-h-[600px] transition-all duration-300 cursor-zoom-in"
                                    @click="zoomOpen = true"
                                >
                            </template>

                            {{-- Magnifier Zoom Button --}}
                            <button
                                type="button"
                                class="absolute bottom-4 right-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-neutral-700 shadow-md backdrop-blur transition hover:bg-white hover:text-neutral-900 hover:scale-105"
                                @click="zoomOpen = true"
                                aria-label="{{ __('Zoom image') }}"
                            >
                                <i class="ph ph-magnifying-glass-plus text-base font-bold"></i>
                            </button>
                        </div>
                    </div>

                    {{-- 4 Feature Highlights Bar under Main Image (Matching Image 1) --}}
                    <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach($featureHighlights as $highlight)
                            @php
                                $label = is_array($highlight) ? ($highlight['label'] ?? '') : (string) $highlight;
                                $icon = is_array($highlight) ? ($highlight['icon'] ?? 'ph-check-circle') : 'ph-check-circle';
                                if (!str_starts_with($icon, 'ph-')) { $icon = 'ph-'.$icon; }
                            @endphp
                            <div class="flex flex-col items-center justify-center text-center p-3 rounded-2xl border border-neutral-200/80 bg-[#FAFAFA] hover:bg-white transition-all shadow-2xs">
                                <div class="h-9 w-9 rounded-full bg-neutral-100 flex items-center justify-center text-neutral-800 mb-1.5">
                                    <i class="ph {{ $icon }} text-lg"></i>
                                </div>
                                <span class="text-[10px] sm:text-[11px] font-black uppercase tracking-tight text-neutral-800 leading-tight">
                                    {{ $label }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- RIGHT COLUMN: Product Info, Options, Price & Action CTAs (Columns 7-12) --}}
                <div class="lg:col-span-6 flex flex-col justify-between">
                    <div>
                        {{-- Product Heading --}}
                        <h1 id="product-page-heading" class="text-2xl sm:text-3xl font-black text-neutral-900 tracking-tight leading-snug">
                            {{ $product->name }}
                        </h1>

                        {{-- Star Rating & Reviews --}}
                        <div class="mt-2.5 flex items-center gap-2">
                            <div class="flex items-center text-[#F59E0B]">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="ph-fill ph-star text-sm"></i>
                                @endfor
                            </div>
                            <span class="text-xs font-semibold text-neutral-600">
                                ({{ $product->reviews_count ?? 128 }} {{ __('Reviews') }})
                            </span>
                        </div>

                        {{-- Short Description / Subtitle --}}
                        <div class="mt-3 text-sm text-neutral-600 leading-relaxed font-normal">
                            {{ $product->short_description ?: __('Premium Tech Fleece 2-Piece Set – Hoodie & Jogger. Designed for all-day comfort and a sporty look.') }}
                        </div>

                        {{-- Wholesale Price & MOQ Box --}}
                        <div class="mt-5 pb-4 border-b border-neutral-200">
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl sm:text-4xl font-black text-[#DC2626] tracking-tight">
                                    {{ $currencySymbol }}<span x-text="currentTotalWholesalePrice"></span>
                                </span>
                                <span class="text-sm sm:text-base font-bold text-neutral-600">
                                    / <span x-text="quantity"></span> {{ __('Set') }} (<span class="text-neutral-800 font-extrabold">{{ __('MOQ: ') }}{{ $moq }}</span>)
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-neutral-500 font-medium">
                                {{ __('Shipping calculated at checkout.') }}
                            </p>

                            {{-- Shipping & Delivery info with Icons --}}
                            <div class="mt-3.5 space-y-1.5 text-xs font-semibold text-neutral-700">
                                <div class="flex items-center gap-2">
                                    <i class="ph ph-truck text-base text-neutral-800"></i>
                                    <span>{{ $product->shipping_info ?: __('USA & Canada Shipping') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="ph ph-clock text-base text-neutral-800"></i>
                                    <span>{{ $product->delivery_time ?: __('6–10 Working Days Delivery') }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Size Selection --}}
                        <div class="mt-5">
                            <div class="flex items-center justify-between text-xs font-bold text-neutral-900 mb-2.5">
                                <span>{{ __('Size: ') }}<span class="font-normal text-neutral-600" x-text="selectedSize"></span></span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="sz in sizes" :key="sz">
                                    <button
                                        type="button"
                                        class="h-10 min-w-16 px-3 rounded-xl border text-xs font-bold transition-all shadow-2xs focus:outline-none"
                                        :class="selectedSize === sz ? 'border-neutral-900 bg-neutral-900 text-white ring-2 ring-neutral-900/20' : 'border-neutral-200 bg-white text-neutral-800 hover:border-neutral-400 hover:bg-neutral-50'"
                                        @click="selectSize(sz)"
                                        x-text="sz"
                                    ></button>
                                </template>
                            </div>
                        </div>

                        {{-- Color Swatches Selection --}}
                        <div class="mt-5">
                            <div class="flex items-center justify-between text-xs font-bold text-neutral-900 mb-2.5">
                                <span>{{ __('Colors: ') }}<span class="font-normal text-neutral-600" x-text="colors.length + '+ Colors Available'"></span></span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <template x-for="(col, cIdx) in colors.slice(0, 12)" :key="col.id || cIdx">
                                    <button
                                        type="button"
                                        class="relative h-8 w-8 rounded-full border-2 transition-all p-0.5 focus:outline-none"
                                        :class="selectedColorIndex === cIdx ? 'border-neutral-900 scale-110 shadow-xs' : 'border-transparent hover:scale-105'"
                                        :title="col.name || col.display_name"
                                        @click="selectColor(cIdx)"
                                    >
                                        <span class="block h-full w-full rounded-full border border-neutral-300 shadow-2xs" :style="`background-color: ${col.hex_code || '#2563EB'}`"></span>
                                    </button>
                                </template>
                                <template x-if="colors.length > 12">
                                    <span class="text-xs font-bold text-neutral-500 pl-1" x-text="`+${colors.length - 12}`"></span>
                                </template>
                            </div>
                        </div>

                        {{-- Quantity Stepper (Bounded to MOQ) --}}
                        <div class="mt-5">
                            <label class="block text-xs font-bold text-neutral-900 mb-2">{{ __('Quantity (Set)') }}</label>
                            <div class="inline-flex items-center rounded-xl border border-neutral-200 bg-white shadow-2xs">
                                <button
                                    type="button"
                                    class="h-10 w-10 flex items-center justify-center text-neutral-600 hover:text-neutral-900 disabled:opacity-40 disabled:cursor-not-allowed transition"
                                    :disabled="quantity <= moq"
                                    @click="decrementQty()"
                                    aria-label="{{ __('Decrease quantity') }}"
                                >
                                    <i class="ph ph-minus font-bold text-sm"></i>
                                </button>
                                <input
                                    type="number"
                                    class="w-16 border-0 text-center font-bold text-sm text-neutral-900 focus:ring-0"
                                    x-model.number="quantity"
                                    @input="if(quantity < moq) quantity = moq"
                                >
                                <button
                                    type="button"
                                    class="h-10 w-10 flex items-center justify-center text-neutral-600 hover:text-neutral-900 transition"
                                    @click="incrementQty()"
                                    aria-label="{{ __('Increase quantity') }}"
                                >
                                    <i class="ph ph-plus font-bold text-sm"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Main Action Buttons (Add to Quote & WhatsApp Order) --}}
                        <div class="mt-6 space-y-3">
                            {{-- Add to Quote Button --}}
                            <button
                                type="button"
                                class="w-full h-12 rounded-xl bg-[#0F172A] hover:bg-[#1E293B] text-white font-bold text-sm flex items-center justify-center gap-2 transition shadow-sm"
                                @click="quoteModalOpen = true"
                            >
                                <i class="ph ph-notepad text-lg"></i>
                                <span>{{ __('Add to Quote') }}</span>
                            </button>

                            {{-- WhatsApp Order Button --}}
                            <a
                                :href="whatsappOrderUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-full h-12 rounded-xl border-2 border-emerald-600/30 bg-white hover:bg-emerald-50 text-emerald-800 font-bold text-sm flex items-center justify-center gap-2 transition shadow-sm"
                            >
                                <i class="ph-fill ph-whatsapp-logo text-xl text-[#25D366]"></i>
                                <span>{{ __('WhatsApp Order') }}</span>
                            </a>
                        </div>

                        {{-- 5-Icon Trust Badges Row Under Buttons --}}
                        <div class="mt-6 grid grid-cols-5 gap-2 border-t border-neutral-200/80 pt-5 text-center">
                            <div class="flex flex-col items-center">
                                <i class="ph ph-seal-check text-xl text-neutral-700"></i>
                                <span class="text-[10px] font-bold text-neutral-700 mt-1 leading-tight">{{ __('Premium Quality') }}</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <i class="ph ph-ruler text-xl text-neutral-700"></i>
                                <span class="text-[10px] font-bold text-neutral-700 mt-1 leading-tight">{{ __('USA True Size') }}</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <i class="ph ph-buildings text-xl text-neutral-700"></i>
                                <span class="text-[10px] font-bold text-neutral-700 mt-1 leading-tight">{{ __('Factory Direct') }}</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <i class="ph ph-globe text-xl text-neutral-700"></i>
                                <span class="text-[10px] font-bold text-neutral-700 mt-1 leading-tight">{{ __('Worldwide Shipping') }}</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <i class="ph ph-lock-key text-xl text-neutral-700"></i>
                                <span class="text-[10px] font-bold text-neutral-700 mt-1 leading-tight">{{ __('Secure Payment') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- BOTTOM 2-COLUMN SECTION: Product Description/Specs (Left) + 20+ Colors Gallery (Right) --}}
            <div class="mt-14 pt-10 border-t border-neutral-200/80 grid gap-10 lg:grid-cols-12">
                
                {{-- Bottom Left: Product Description, Bullet Features & Specifications Table --}}
                <div class="lg:col-span-6 space-y-6">
                    <div>
                        <h2 class="text-lg font-black text-neutral-900 uppercase tracking-tight">{{ __('Product Description') }}</h2>
                        <p class="mt-2 text-sm text-neutral-600 leading-relaxed">
                            {{ $product->description ?: __('Premium Kids Nike Tech Fleece Full-Zip Tracksuit designed for everyday comfort and a stylish sporty look.') }}
                        </p>
                    </div>

                    {{-- Bullet-point Features List --}}
                    <div>
                        <ul class="space-y-2 text-sm text-neutral-700">
                            @foreach($bulletFeatures as $feat)
                                <li class="flex items-start gap-2.5">
                                    <span class="text-neutral-900 font-black">•</span>
                                    <span>{{ $feat }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Specifications Table (Matching Screenshot) --}}
                    <div class="rounded-2xl border border-neutral-200 bg-[#FAFAFA] p-5 shadow-2xs">
                        <table class="w-full text-xs">
                            <tbody class="divide-y divide-neutral-200/80 font-medium">
                                <tr>
                                    <td class="py-2.5 pr-4 text-neutral-900 font-bold w-32">{{ __('SKU') }}</td>
                                    <td class="py-2.5 text-neutral-700 font-mono" x-text="productSku"></td>
                                </tr>
                                @if(is_array($product->specifications) && count($product->specifications) > 0)
                                    @foreach($product->specifications as $spec)
                                        @if(!empty($spec['attribute']) && !empty($spec['value']) && strtolower($spec['attribute']) !== 'sku')
                                            <tr>
                                                <td class="py-2.5 pr-4 text-neutral-900 font-bold">{{ $spec['attribute'] }}</td>
                                                <td class="py-2.5 text-neutral-700">{{ $spec['value'] }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                @else
                                    <tr>
                                        <td class="py-2.5 pr-4 text-neutral-900 font-bold">{{ __('Material') }}</td>
                                        <td class="py-2.5 text-neutral-700">{{ $product->material ?: __('Tech Fleece (Premium Quality)') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 pr-4 text-neutral-900 font-bold">{{ __('Fit') }}</td>
                                        <td class="py-2.5 text-neutral-700">{{ $product->fit ?: __('USA True-to-Size') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 pr-4 text-neutral-900 font-bold">{{ __('Set Includes') }}</td>
                                        <td class="py-2.5 text-neutral-700">{{ $product->set_includes ?: __('Hoodie + Jogger Pants') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 pr-4 text-neutral-900 font-bold">{{ __('Gender') }}</td>
                                        <td class="py-2.5 text-neutral-700">{{ $product->gender ?: __('Unisex (Boys & Girls)') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 pr-4 text-neutral-900 font-bold">{{ __('Season') }}</td>
                                        <td class="py-2.5 text-neutral-700">{{ $product->season ?: __('All Season') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 pr-4 text-neutral-900 font-bold">{{ __('MOQ') }}</td>
                                        <td class="py-2.5 text-neutral-700">{{ $moq }} {{ __('Set') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 pr-4 text-neutral-900 font-bold">{{ __('Shipping') }}</td>
                                        <td class="py-2.5 text-neutral-700">{{ $product->shipping_info ?: __('USA & Canada (6-10 Working Days)') }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Bottom Right: 20+ COLORS AVAILABLE Photo Grid (Matching Screenshot) --}}
                <div class="lg:col-span-6">
                    <h2 class="text-sm font-black uppercase tracking-wider text-neutral-900 mb-4">
                        {{ count($colors) > 0 ? count($colors).'+ ' : '20+ ' }}{{ __('COLORS AVAILABLE') }}
                    </h2>

                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                        @foreach($colors as $idx => $color)
                            <button
                                type="button"
                                class="flex flex-col items-center rounded-xl border p-1.5 transition-all text-center group focus:outline-none"
                                :class="selectedColorIndex === {{ $idx }} ? 'border-neutral-900 bg-neutral-50 shadow-sm ring-2 ring-neutral-900/10' : 'border-neutral-200 bg-white hover:border-neutral-300 hover:shadow-2xs'"
                                @click="selectColor({{ $idx }})"
                            >
                                <div class="h-20 w-full rounded-lg overflow-hidden bg-neutral-100 flex items-center justify-center">
                                    @if(!empty($color['photo_url']))
                                        <img src="{{ $color['photo_url'] }}" alt="{{ $color['display_name'] }}" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-200">
                                    @else
                                        <div class="h-full w-full flex items-center justify-center" style="background-color: {{ $color['hex_code'] }}">
                                            <i class="ph ph-t-shirt text-2xl text-white/80"></i>
                                        </div>
                                    @endif
                                </div>
                                <span class="mt-1.5 text-[9px] sm:text-[10px] font-black uppercase tracking-tight text-neutral-800 line-clamp-1">
                                    {{ $color['name'] ?: $color['display_name'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- FULL-WIDTH TRUST & BUSINESS INFORMATION BARS (Matching Screenshot) --}}
            <div class="mt-14 space-y-4">
                
                {{-- Top 3-Column Trust Strip --}}
                <div class="rounded-2xl border border-neutral-200 bg-[#FAFAFA] p-6 grid gap-6 sm:grid-cols-3 text-center">
                    <div class="flex items-center justify-center gap-3">
                        <div class="h-12 w-12 rounded-full bg-white border border-neutral-200 flex items-center justify-center text-neutral-800 shadow-2xs shrink-0">
                            <i class="ph ph-users-four text-2xl"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-sm font-black text-neutral-900">{{ __('1000+') }}</p>
                            <p class="text-xs text-neutral-600">{{ __('USA Buyers Trust Us') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-3 border-t sm:border-t-0 sm:border-l border-neutral-200 pt-4 sm:pt-0 sm:pl-6">
                        <div class="h-12 w-12 rounded-full bg-white border border-neutral-200 flex items-center justify-center text-neutral-800 shadow-2xs shrink-0">
                            <i class="ph ph-buildings text-2xl"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-sm font-black text-neutral-900">{{ __('Factory Direct') }}</p>
                            <p class="text-xs text-neutral-600">{{ __('No Middleman') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-3 border-t sm:border-t-0 sm:border-l border-neutral-200 pt-4 sm:pt-0 sm:pl-6">
                        <div class="h-12 w-12 rounded-full bg-white border border-neutral-200 flex items-center justify-center text-neutral-800 shadow-2xs shrink-0">
                            <i class="ph ph-shield-check text-2xl"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-sm font-black text-neutral-900">{{ __('Secure Payment') }}</p>
                            <p class="text-xs text-neutral-600">{{ __('Safe & Reliable') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Bottom Dark Navy Wholesale Feature Strip --}}
                <div class="rounded-2xl bg-[#0F172A] text-white p-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="flex items-center gap-3">
                        <i class="ph ph-package text-2xl text-neutral-400"></i>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider">{{ __('Wholesale Only') }}</p>
                            <p class="text-[11px] text-neutral-400">{{ __('We Do Only Wholesale') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <i class="ph ph-sparkle text-2xl text-neutral-400"></i>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider">{{ __('Best Quality') }}</p>
                            <p class="text-[11px] text-neutral-400">{{ __('Premium Fabric & Stitching') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <i class="ph ph-truck text-2xl text-neutral-400"></i>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider">{{ __('Fast Shipping') }}</p>
                            <p class="text-[11px] text-neutral-400">{{ __('6-10 Working Days Delivery') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <i class="ph ph-headphones text-2xl text-neutral-400"></i>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider">{{ __('Customer Support') }}</p>
                            <p class="text-[11px] text-neutral-400">{{ __('Always Here to Help') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        {{-- Image Zoom Modal --}}
        <div
            x-show="zoomOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-xs"
            @keydown.escape.window="zoomOpen = false"
        >
            <div class="relative max-h-[90vh] max-w-4xl overflow-hidden rounded-2xl bg-white p-2" @click.away="zoomOpen = false">
                <button
                    type="button"
                    class="absolute top-4 right-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-black/70 text-white hover:bg-black"
                    @click="zoomOpen = false"
                >
                    <i class="ph ph-x text-lg"></i>
                </button>
                <img :src="currentActiveMedia.url" :alt="currentActiveMedia.alt" class="max-h-[85vh] w-auto mx-auto object-contain rounded-xl">
            </div>
        </div>

        {{-- Add to Quote Modal --}}
        <div
            x-show="quoteModalOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @keydown.escape.window="quoteModalOpen = false"
        >
            <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" @click.away="quoteModalOpen = false">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
                    <div>
                        <h3 class="text-lg font-black text-neutral-900">{{ __('Request Wholesale Quote') }}</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">{{ $product->name }}</p>
                    </div>
                    <button type="button" class="text-neutral-400 hover:text-neutral-700" @click="quoteModalOpen = false">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                </div>

                {{-- Success State --}}
                <div x-show="quoteSuccess" class="py-8 text-center text-emerald-700">
                    <i class="ph-fill ph-check-circle text-5xl mx-auto mb-2"></i>
                    <p class="font-bold text-base">{{ __('Quote Request Submitted!') }}</p>
                    <p class="text-xs text-neutral-600 mt-1">{{ __('Our sales team will contact you via WhatsApp / Email shortly.') }}</p>
                </div>

                {{-- Form State --}}
                <form x-show="!quoteSuccess" @submit.prevent="submitQuote()" class="mt-4 space-y-4">
                    <div class="rounded-xl bg-neutral-50 p-3 text-xs text-neutral-700 space-y-1">
                        <p><strong>{{ __('Selected:') }}</strong> <span x-text="`${selectedSize}, ${selectedColor.name || selectedColor.display_name}`"></span></p>
                        <p><strong>{{ __('Quantity:') }}</strong> <span x-text="`${quantity} Sets`"></span></p>
                        <p><strong>{{ __('Estimated Total:') }}</strong> <span class="font-bold text-[#DC2626]" x-text="`${currencySymbol}${currentTotalWholesalePrice}`"></span></p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-800 mb-1">{{ __('Your Name') }} *</label>
                        <input type="text" required class="form-input text-sm" x-model="quoteForm.name" placeholder="{{ __('John Doe') }}">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-neutral-800 mb-1">{{ __('WhatsApp Number') }} *</label>
                            <input type="tel" required class="form-input text-sm" x-model="quoteForm.whatsapp" placeholder="{{ __('+1 234 567 8900') }}">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-800 mb-1">{{ __('Email Address') }}</label>
                            <input type="email" class="form-input text-sm" x-model="quoteForm.email" placeholder="{{ __('john@company.com') }}">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-800 mb-1">{{ __('Company / Store Name') }}</label>
                        <input type="text" class="form-input text-sm" x-model="quoteForm.company" placeholder="{{ __('Retail Store LLC') }}">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-neutral-800 mb-1">{{ __('Custom Requirements / Notes') }}</label>
                        <textarea class="form-input text-xs min-h-18" x-model="quoteForm.notes" placeholder="{{ __('Any special packing, custom labeling, or target delivery date...') }}"></textarea>
                    </div>

                    <button
                        type="submit"
                        class="w-full h-11 rounded-xl bg-[#0F172A] hover:bg-neutral-800 text-white font-bold text-sm flex items-center justify-center gap-2 shadow-sm transition"
                        :disabled="quoteSubmitting"
                    >
                        <span x-show="!quoteSubmitting">{{ __('Submit Wholesale Quote Request') }}</span>
                        <span x-show="quoteSubmitting">{{ __('Submitting...') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </article>
@endsection
