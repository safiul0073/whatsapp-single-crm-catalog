@php
    $isEdit = (bool) $product;
    $galleryState = $product ? $product->gallery->map(fn ($item) => [
        'id' => $item->media_id,
        'name' => $item->media?->name,
        'url' => $item->media?->url,
        'type' => $item->media_type,
        'alt_text' => $item->alt_text,
        'color_id' => $item->color_id,
        'is_primary' => $item->is_primary,
    ])->values()->all() : [];

    $colorState = $product ? $product->colors->map(fn ($color) => [
        'id' => $color->id,
        'name' => $color->name ?? '',
        'hex_code' => $color->hex_code ?: '#2563EB',
        'color_family' => $color->color_family ?? '',
        'swatch_media_id' => $color->swatch_media_id,
        'swatch_image_url' => $color->swatchMedia?->url,
        'display_name' => $color->display_name,
    ])->values()->all() : [];

    $tierPricesState = $product ? $product->tierPrices->map(fn ($tp) => [
        'min_quantity' => $tp->min_quantity,
        'max_quantity' => $tp->max_quantity,
        'unit_price' => $tp->unit_price,
        'discount_percentage' => $tp->discount_percentage,
    ])->values()->all() : [];

    $sizeOption = $product?->options->first(fn ($o) => strtolower($o->code) === 'size' || strtolower($o->name) === 'size');
    $sizeState = $sizeOption ? $sizeOption->values->map(function ($v) {
        return [
            'value' => $v->value,
            'weight' => $v->weight,
            'weight_unit' => $v->weight_unit ?? 'kg',
        ];
    })->all() : ($product ? $product->variants->pluck('size')->filter()->unique()->map(fn($s) => ['value' => $s, 'weight' => null, 'weight_unit' => 'kg'])->values()->all() : [
        ['value' => '1YRS', 'weight' => null, 'weight_unit' => 'kg'],
        ['value' => '2YRS', 'weight' => null, 'weight_unit' => 'kg'],
        ['value' => '4YRS', 'weight' => null, 'weight_unit' => 'kg'],
        ['value' => '6YRS', 'weight' => null, 'weight_unit' => 'kg'],
        ['value' => '8YRS', 'weight' => null, 'weight_unit' => 'kg'],
        ['value' => '10YRS', 'weight' => null, 'weight_unit' => 'kg'],
        ['value' => '12YRS', 'weight' => null, 'weight_unit' => 'kg'],
        ['value' => '14YRS', 'weight' => null, 'weight_unit' => 'kg'],
    ]);

    $featureHighlightsState = is_array($product?->feature_highlights) && count($product->feature_highlights) > 0
        ? $product->feature_highlights
        : [
            ['label' => 'Premium Tech Fleece', 'icon' => 'ph-t-shirt'],
        ];

    $shippingCountriesState = is_array($product?->shipping_countries) && count($product->shipping_countries) > 0
        ? $product->shipping_countries
        : ['USA', 'Canada'];

    $specificationsState = is_array($product?->specifications) && count($product->specifications) > 0
        ? $product->specifications
        : [
            ['attribute' => 'Material', 'value' => $product?->material ?: 'Tech Fleece (Premium Quality)'],
            ['attribute' => 'Fit', 'value' => $product?->fit ?: 'USA True-to-Size'],
            ['attribute' => 'Set Includes', 'value' => $product?->set_includes ?: 'Hoodie + Jogger Pants'],
            ['attribute' => 'Gender', 'value' => $product?->gender ?: 'Unisex (Boys & Girls)'],
            ['attribute' => 'Season', 'value' => $product?->season ?: 'All Season'],
            ['attribute' => 'MOQ', 'value' => ($product?->moq ?: '40').' Set'],
            ['attribute' => 'Shipping', 'value' => $product?->shipping_info ?: 'USA & Canada (6-10 Working Days)'],
        ];

    $currentStep = (int) ($step ?? 1);
@endphp

<x-layouts.user :title="$isEdit ? __('Edit product — :name', ['name' => $product->name]) : __('Add Product')" :hide-help="true">
    <div
        class="mx-auto max-w-7xl space-y-6"
        x-data="commerceProductWizard(@js([
            'gallery' => $galleryState,
            'colors' => $colorState,
            'sizes' => $sizeState,
            'tierPrices' => $tierPricesState,
            'featureHighlights' => $featureHighlightsState,
            'shippingCountries' => $shippingCountriesState,
            'specifications' => $specificationsState,
            'variants' => $variantPreview ?? [],
            'variantPresets' => $variantPresets ?? [],
            'basePrice' => (float) ($product?->single_piece_price ?? 9.00),
            'productSlug' => $product?->slug ?? 'PROD',
            'previewUrl' => $isEdit ? route('user.commerce.products.variants.preview', $product) : null
        ]))"
        @change="dirty = true"
    >
        {{-- Hidden Media Picker Bridge: connects Alpine actions to DOM-based media picker --}}
        <div id="commerceMediaPickerBridge" class="hidden" data-media-picker data-media-accept="image" data-media-multiple="true">
            <button type="button" data-media-picker-trigger></button>
        </div>
        {{-- Header Bar --}}
        <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between pb-2">
            <div>
                <nav class="flex items-center gap-1.5 text-xs font-medium text-neutral-500 mb-1" aria-label="Breadcrumb">
                    <a href="{{ route('user.commerce.products.index') }}" class="hover:text-neutral-900 transition-colors">{{ __('Products') }}</a>
                    <span class="text-neutral-400">/</span>
                    <span class="text-neutral-800 font-semibold">{{ $isEdit ? __('Edit Product') : __('Add Product') }}</span>
                </nav>
                <h1 class="text-2xl font-black tracking-tight text-neutral-900">{{ $isEdit ? $product->name : __('Add Product') }}</h1>
            </div>
            @if($isEdit)
                <div class="flex items-center gap-2">
                    <a href="{{ route('commerce.products.direct', ['product' => $product->slug]) }}" target="_blank" class="btn btn-sm btn-outline text-xs inline-flex items-center gap-1.5 shadow-2xs">
                        <i class="ph ph-arrow-square-out text-sm"></i>
                        <span>{{ __('View Storefront') }}</span>
                    </a>
                </div>
            @endif
        </header>

        {{-- Screenshot-Matched Tab Navigation --}}
        <div class="w-full overflow-x-auto no-scrollbar -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 pt-4 border-b border-neutral-200 bg-white mb-8">
            <nav class="flex items-end justify-between min-w-[800px] w-full" aria-label="Progress">
                @php
                    $steps = [
                        1 => ['label' => __('Basic Info')],
                        2 => ['label' => __('Images')],
                        3 => ['label' => __('Sizes')],
                        4 => ['label' => __('Colors')],
                        5 => ['label' => __('Pricing & MOQ')],
                        6 => ['label' => __('Features')],
                        7 => ['label' => __('Description')],
                        8 => ['label' => __('Specifications')],
                    ];
                @endphp

                @foreach($steps as $stepNum => $sData)
                    @php
                        $isActive = $currentStep === $stepNum;
                        $isAccessible = $isEdit || $stepNum === 1;
                        $isCompleted = $isEdit && $product->wizard_step > $stepNum;
                        $prevCompleted = $isEdit && $product->wizard_step > ($stepNum - 1);
                        $url = $isEdit ? route('user.commerce.products.edit', ['product' => $product, 'step' => $stepNum]) : '#';
                    @endphp
                    
                    <a href="{{ $isAccessible ? $url : '#' }}" class="relative flex flex-col items-center gap-2.5 px-6 pt-5 pb-4 transition-colors flex-1 {{ $isActive ? 'bg-primary/5 rounded-t-xl' : ($isAccessible ? 'hover:bg-neutral-50 rounded-t-xl' : 'opacity-60 cursor-not-allowed') }}">
                        
                        {{-- Active Bottom Border --}}
                        @if($isActive)
                            <div class="absolute bottom-0 left-0 w-full h-[3px] bg-primary rounded-t-sm z-20"></div>
                        @endif

                        {{-- Left Half Line --}}
                        @if(!$loop->first)
                            <div class="absolute left-0 w-[50%] top-[36px] h-[2px] -translate-y-1/2 z-0 {{ $prevCompleted ? 'bg-primary' : 'bg-neutral-200' }}"></div>
                        @endif

                        {{-- Right Half Line --}}
                        @if(!$loop->last)
                            <div class="absolute right-0 w-[50%] top-[36px] h-[2px] -translate-y-1/2 z-0 {{ $isCompleted ? 'bg-primary' : 'bg-neutral-200' }}"></div>
                        @endif

                        {{-- Circle --}}
                        <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs transition-colors {{ $isActive || $isCompleted ? 'bg-primary text-white shadow-sm shadow-primary/20' : ($isAccessible ? 'bg-white border border-neutral-300 text-neutral-500' : 'bg-white border border-neutral-200 text-neutral-400') }}">
                            @if($isCompleted)
                                <i class="ph-bold ph-check text-sm"></i>
                            @else
                                <span class="font-bold">{{ $stepNum }}</span>
                            @endif
                        </span>
                        
                        {{-- Label --}}
                        <span class="relative z-10 text-[11px] font-bold tracking-wide whitespace-nowrap {{ $isActive ? 'text-neutral-900' : ($isCompleted ? 'text-neutral-700' : 'text-neutral-500') }}">
                            {{ $sData['label'] }}
                        </span>
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Validation Error Alert --}}
        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-800 shadow-xs">
                <div class="flex items-center gap-2 font-bold text-sm mb-1.5">
                    <i class="ph ph-warning-circle text-lg text-red-600"></i>
                    <span>{{ __('Please correct the following errors before proceeding:') }}</span>
                </div>
                <ul class="list-disc list-inside text-xs text-red-700 space-y-1 ml-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- STEP 1: Basic Information --}}
        @if($currentStep === 1)
            <form method="POST" action="{{ $isEdit ? route('user.commerce.products.details.update', $product) : route('user.commerce.products.store') }}" class="space-y-6" @submit="markSaved()">
                @csrf @if($isEdit) @method('PUT') @endif
                <input type="hidden" name="next_step" value="2">

                <section class="rounded-2xl border border-neutral-200/80 bg-white p-6 sm:p-8 shadow-xs">
                    <h2 class="text-lg font-bold text-neutral-900">{{ __('Basic Information') }}</h2>
                    <p class="text-xs text-neutral-500 mt-0.5">{{ __('Enter the core title, SKU, short excerpt description, and prices for this product.') }}</p>

                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        {{-- Product Name --}}
                        <div>
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="product_name">{{ __('Product Name *') }}</label>
                            <input id="product_name" class="form-input text-sm @error('name') border-red-500 ring-1 ring-red-500 @enderror" name="name" required maxlength="255" value="{{ old('name', $product?->name) }}" placeholder="{{ __('Nike Sportswear Tech Fleece Kids Full-Zip Tracksuit') }}">
                            @error('name')
                                <p class="text-xs font-semibold text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- SKU --}}
                        <div>
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="product_sku">{{ __('SKU *') }}</label>
                            <input id="product_sku" class="form-input text-sm font-mono uppercase @error('sku') border-red-500 ring-1 ring-red-500 @enderror" name="sku" maxlength="120" value="{{ old('sku', $product?->sku ?? ('NTFKIDS-'.str_pad((string)($product?->id ?? 1), 3, '0', STR_PAD_LEFT))) }}" placeholder="{{ __('NTFKIDS-001') }}">
                            @error('sku')
                                <p class="text-xs font-semibold text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Short Description --}}
                        <div class="md:col-span-2">
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="short_description">{{ __('Short Description *') }}</label>
                            <textarea id="short_description" class="form-input text-sm min-h-20 @error('short_description') border-red-500 ring-1 ring-red-500 @enderror" name="short_description" maxlength="1000" placeholder="{{ __('Premium Tech Fleece 2-Piece Set – Hoodie & Jogger. Designed for all-day comfort and a sporty look.') }}">{{ old('short_description', $product?->short_description) }}</textarea>
                            @error('short_description')
                                <p class="text-xs font-semibold text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Wholesale Price --}}
                        <div class="rounded-xl border border-emerald-500/30 bg-emerald-50/30 p-4">
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-emerald-800" for="wholesale_price">{{ __('Wholesale Price *') }}</label>
                            <div class="flex items-center gap-2 mt-1">
                                <input id="wholesale_price" type="number" step="0.01" min="0.01" class="form-input font-bold text-emerald-700 flex-1 @error('wholesale_price') border-red-500 ring-1 ring-red-500 @enderror" name="wholesale_price" required value="{{ old('wholesale_price', $product?->wholesale_price ?? "") }}" placeholder="800">
                                <span class="rounded-lg bg-emerald-100 px-3 py-2 text-xs font-bold text-emerald-800">USD</span>
                            </div>
                            @error('wholesale_price')
                                <p class="text-xs font-semibold text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Retail Price --}}
                        <div class="rounded-xl border border-border/80 bg-neutral-50/50 p-4">
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="single_piece_price">{{ __('Retail Price') }}</label>
                            <div class="flex items-center gap-2 mt-1">
                                <input id="single_piece_price" type="number" step="0.01" min="0.01" class="form-input font-bold flex-1 @error('single_piece_price') border-red-500 ring-1 ring-red-500 @enderror" name="single_piece_price" value="{{ old('single_piece_price', $product?->single_piece_price ?? "") }}" placeholder="1200">
                                <span class="rounded-lg bg-neutral-200 px-3 py-2 text-xs font-bold text-neutral-700">USD</span>
                            </div>
                            @error('single_piece_price')
                                <p class="text-xs font-semibold text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Category --}}
                        <div>
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="category_id">{{ __('Category') }}</label>
                            @php
                                $selectedCategoryId = old('category_id', $product?->category_id ?? request('category_id') ?? $categories->first()?->id);
                            @endphp
                            <select id="category_id" class="form-input text-sm cursor-pointer" name="category_id">
                                <option value="">{{ __('Select category') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string)$selectedCategoryId === (string)$category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Brand --}}
                        <div>
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="brand_id">{{ __('Brand') }}</label>
                            @php
                                $selectedBrandId = old('brand_id', $product?->brand_id ?? request('brand_id') ?? $brands->first()?->id);
                            @endphp
                            <select id="brand_id" class="form-input text-sm cursor-pointer" name="brand_id">
                                <option value="">{{ __('Select brand') }}</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected((string)$selectedBrandId === (string)$brand->id)>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Audience --}}
                        <div>
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="audience_id">{{ __('Audience / Target') }}</label>
                            @php
                                $selectedAudienceId = old('audience_id', $product?->audience_id ?? request('audience_id') ?? $audiences->first()?->id);
                            @endphp
                            <select id="audience_id" class="form-input text-sm cursor-pointer" name="audience_id">
                                <option value="">{{ __('Select audience') }}</option>
                                @foreach($audiences as $audience)
                                    <option value="{{ $audience->id }}" @selected((string)$selectedAudienceId === (string)$audience->id)>{{ $audience->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Status --}}
                        <div>
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="status">{{ __('Status') }}</label>
                            <select id="status" class="form-input text-sm cursor-pointer" name="status">
                                <option value="active" @selected(old('status', $product?->status ?? 'active') === 'active')>{{ __('Active') }}</option>
                                <option value="draft" @selected(old('status', $product?->status) === 'draft')>{{ __('Draft') }}</option>
                                <option value="archived" @selected(old('status', $product?->status) === 'archived')>{{ __('Archived') }}</option>
                            </select>
                        </div>

                        {{-- Visibility --}}
                        <div>
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="visibility">{{ __('Visibility') }}</label>
                            <select id="visibility" class="form-input text-sm" name="visibility">
                                <option value="published" @selected(old('visibility', $product?->visibility ?? 'published') === 'published')>{{ __('Published') }}</option>
                                <option value="hidden" @selected(old('visibility', $product?->visibility) === 'hidden')>{{ __('Hidden') }}</option>
                            </select>
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="submit" class="btn btn-primary px-6 py-2.5 font-bold shadow-sm">
                        {{ $isEdit ? __('Save & Next: Images Gallery →') : __('Create & Next: Images Gallery →') }}
                    </button>
                </div>
            </form>

        {{-- STEP 2: Images Gallery --}}
        @elseif($currentStep === 2)
            <form method="POST" action="{{ route('user.commerce.products.gallery.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                <input type="hidden" name="next_step" value="3">

                <section class="rounded-2xl border border-neutral-200/80 bg-white p-6 sm:p-8 shadow-xs">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-neutral-900">{{ __('Product Images') }}</h2>
                            <p class="text-xs text-neutral-500 mt-0.5">{{ __('Upload product photography. Click star to make primary. Recommended size 800x1000px.') }}</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary text-xs shadow-2xs" @click="openMediaPicker()">
                            <i class="ph ph-upload-simple"></i> {{ __('Upload Image') }}
                        </button>
                    </div>

                    {{-- Image Grid Showcase matching Step 2 --}}
                    <div class="mt-6 grid gap-6 lg:grid-cols-12">
                        {{-- Left Column: Thumbnails List --}}
                        <div class="lg:col-span-3 space-y-3 max-h-[500px] overflow-y-auto pr-2">
                            <template x-for="(item, index) in gallery" :key="item.id">
                                <div
                                    class="flex items-center gap-3 p-2 rounded-xl border transition-all cursor-pointer"
                                    :class="item.is_primary ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-neutral-200 bg-white hover:border-neutral-400'"
                                    @click="setPrimaryById(item.id)"
                                >
                                    <img :src="item.url" :alt="item.alt_text" class="h-14 w-14 rounded-lg object-cover border border-neutral-200">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-neutral-900 truncate" x-text="item.name || `Photo #${index + 1}`"></p>
                                        <span x-show="item.is_primary" class="inline-block mt-0.5 text-[10px] font-bold text-primary">{{ __('Primary') }}</span>
                                    </div>
                                    <button type="button" class="text-neutral-400 hover:text-red-600 p-1" @click.stop="removeMedia(index)">
                                        <i class="ph ph-trash text-sm"></i>
                                    </button>
                                </div>
                            </template>
                            <div x-show="gallery.length === 0" class="text-xs text-neutral-400 p-4 border border-dashed rounded-xl text-center">
                                {{ __('No images added yet.') }}
                            </div>
                        </div>

                        {{-- Center Column: Large Main Preview --}}
                        <div class="lg:col-span-6 rounded-2xl border border-neutral-200 bg-neutral-50 flex items-center justify-center p-4 min-h-[380px] overflow-hidden">
                            <template x-if="gallery.find(g => g.is_primary) || gallery[0]">
                                <img :src="(gallery.find(g => g.is_primary) || gallery[0]).url" alt="Preview" class="max-h-[420px] w-auto rounded-xl object-contain shadow-sm">
                            </template>
                            <div x-show="gallery.length === 0" class="text-center text-neutral-400">
                                <i class="ph ph-image text-4xl text-neutral-300"></i>
                                <p class="mt-2 text-xs font-semibold text-neutral-600">{{ __('No primary image selected') }}</p>
                            </div>
                        </div>

                        {{-- Right Column: Upload Dropzone Card --}}
                        <div class="lg:col-span-3">
                            <div
                                class="h-full min-h-[220px] rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 hover:bg-neutral-100 hover:border-primary p-6 flex flex-col items-center justify-center text-center cursor-pointer transition"
                                @click="openMediaPicker()"
                            >
                                <div class="h-12 w-12 rounded-full bg-white border border-neutral-200 flex items-center justify-center text-neutral-700 shadow-2xs mb-2">
                                    <i class="ph ph-plus text-xl font-bold text-primary"></i>
                                </div>
                                <span class="text-xs font-bold text-neutral-900">{{ __('+ Upload Image') }}</span>
                                <span class="text-[11px] text-neutral-400 mt-1">{{ __('Recommended size') }}<br>800x1000px</span>
                            </div>
                        </div>
                    </div>

                    {{-- Hidden Inputs to submit gallery --}}
                    <template x-for="(item, index) in gallery" :key="item.id">
                        <div>
                            <input type="hidden" :name="`media[${index}][id]`" :value="item.id">
                            <input type="hidden" :name="`media[${index}][is_primary]`" :value="item.is_primary ? '1' : '0'">
                            <input type="hidden" :name="`media[${index}][alt_text]`" :value="item.alt_text || ''">
                            <input type="hidden" :name="`media[${index}][color_id]`" :value="item.color_id || ''">
                        </div>
                    </template>
                </section>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 1]) }}">{{ __('Back') }}</x-ui.button>
                    <button type="submit" class="btn btn-primary px-6 py-2.5 font-bold shadow-sm">{{ __('Save & Next: Sizes →') }}</button>
                </div>
            </form>

        {{-- STEP 3: Sizes (Manage) --}}
        @elseif($currentStep === 3)
            <form method="POST" action="{{ route('user.commerce.products.options.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                <input type="hidden" name="next_step" value="4">
                <input type="hidden" name="options[0][code]" value="size">
                <input type="hidden" name="options[0][name]" value="Size">

                <section class="rounded-2xl border border-neutral-200/80 bg-white p-6 sm:p-8 shadow-xs">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-neutral-900">{{ __('Sizes') }}</h2>
                            <p class="text-xs text-neutral-500 mt-0.5">{{ __('Manage available garment sizes. Admin can add, edit, remove, and reorder sizes.') }}</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary text-xs shadow-2xs" @click="openSizeModal()">
                            <i class="ph ph-plus"></i> {{ __('Add Size') }}
                        </button>
                    </div>

                    {{-- Sizes Interactive Table --}}
                    <div class="mt-5 rounded-xl border border-neutral-200 overflow-hidden shadow-2xs">
                        <div class="divide-y divide-neutral-200">
                            <template x-for="(sz, sIdx) in sizes" :key="sIdx">
                                <div class="flex items-center gap-4 p-3 bg-white hover:bg-neutral-50/50 transition">
                                    <i class="ph ph-dots-six-vertical text-neutral-400 text-base cursor-grab shrink-0"></i>
                                    <div class="grid grid-cols-[2fr_1fr_1fr] gap-3 flex-1 items-center">
                                        <input
                                            type="text"
                                            class="form-input text-xs font-bold h-9 w-full !py-1.5 !px-3"
                                            x-model="sz.value"
                                            required
                                            placeholder="Size Name"
                                        >
                                        <input
                                            type="number"
                                            step="0.001"
                                            class="form-input text-xs font-medium h-9 w-full !py-1.5 !px-3"
                                            x-model="sz.weight"
                                            placeholder="Weight"
                                        >
                                        <select class="form-input text-xs h-9 w-full !py-1 !pl-2.5 !pr-7 cursor-pointer" x-model="sz.weight_unit">
                                            <option value="kg">kg</option>
                                            <option value="g">g</option>
                                            <option value="lb">lb</option>
                                            <option value="oz">oz</option>
                                        </select>
                                    </div>
                                    <button type="button" class="text-neutral-400 hover:text-red-600 p-1.5 transition shrink-0" @click="removeSize(sIdx)" aria-label="{{ __('Delete size') }}">
                                        <i class="ph ph-trash text-base"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <div x-show="sizes.length === 0" class="p-8 text-center text-xs text-red-600 font-medium">
                            {{ __('Please add at least one size.') }}
                        </div>
                    </div>

                    {{-- Hidden Inputs for Sizes --}}
                    <template x-for="(sz, sIdx) in sizes" :key="sIdx">
                        <div>
                            <input type="hidden" :name="`options[0][values][${sIdx}][value]`" :value="sz.value">
                            <input type="hidden" :name="`options[0][values][${sIdx}][weight]`" :value="sz.weight">
                            <input type="hidden" :name="`options[0][values][${sIdx}][weight_unit]`" :value="sz.weight_unit">
                        </div>
                    </template>
                </section>

                {{-- Select / Create Size Modal --}}
                <div x-show="showSizeModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div x-show="showSizeModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-neutral-900/50 transition-opacity" aria-hidden="true" @click="showSizeModal = false"></div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        
                        <div x-show="showSizeModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="relative z-10 inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-neutral-100">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg leading-6 font-bold text-neutral-900" id="modal-title">{{ __('Select or Create Size') }}</h3>
                                    <button type="button" @click="showSizeModal = false" class="text-neutral-400 hover:text-neutral-500">
                                        <i class="ph ph-x text-xl"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="px-4 py-4 sm:p-6 space-y-5 bg-neutral-50/50">
                                {{-- Quick Add Form --}}
                                <div class="bg-white border border-neutral-200 rounded-xl p-4 shadow-xs">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-600 mb-3">{{ __('Quick Create New Size Preset') }}</h4>
                                    <div class="grid grid-cols-[2fr_1fr_1fr_auto] gap-2 items-center">
                                        <input type="text" class="form-input text-xs h-9 !py-1.5 !px-3" x-model="modalNewSize.name" placeholder="Size Name (e.g. XL)" @keydown.enter.prevent="createPresetFromModal()">
                                        <input type="number" step="0.001" class="form-input text-xs h-9 !py-1.5 !px-3" x-model="modalNewSize.weight" placeholder="Weight" @keydown.enter.prevent="createPresetFromModal()">
                                        <select class="form-input text-xs h-9 !py-1 !pl-2.5 !pr-7 cursor-pointer" x-model="modalNewSize.weight_unit">
                                            <option value="kg">kg</option>
                                            <option value="g">g</option>
                                            <option value="lb">lb</option>
                                            <option value="oz">oz</option>
                                        </select>
                                        <button type="button" class="btn btn-sm btn-primary h-9 px-3" @click="createPresetFromModal()" :disabled="modalSaving">
                                            <i class="ph ph-check" x-show="!modalSaving"></i>
                                            <i class="ph ph-spinner animate-spin" x-show="modalSaving" style="display:none"></i>
                                        </button>
                                    </div>
                                    <p class="text-[10px] text-red-500 mt-1" x-show="modalError" x-text="modalError" style="display:none;"></p>
                                </div>

                                {{-- Preset List --}}
                                <div>
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-600 mb-3">{{ __('Select Reusable Presets') }}</h4>
                                    <div class="max-h-60 overflow-y-auto border border-neutral-200 rounded-xl bg-white shadow-xs divide-y divide-neutral-100">
                                        <template x-for="(vp, idx) in variantPresets" :key="vp.id || idx">
                                            <label class="flex items-center gap-3 p-3 hover:bg-neutral-50 cursor-pointer transition">
                                                <input type="checkbox" class="form-checkbox text-primary rounded shadow-xs" :value="vp" x-model="modalSelectedPresets">
                                                <div class="flex-1 flex justify-between items-center">
                                                    <span class="text-sm font-semibold text-neutral-900" x-text="vp.name"></span>
                                                    <span class="text-xs text-neutral-500 font-medium" x-text="vp.weight ? `${vp.weight} ${vp.weight_unit || 'kg'}` : ''"></span>
                                                </div>
                                            </label>
                                        </template>
                                        <div x-show="variantPresets.length === 0" class="p-4 text-center text-xs text-neutral-400">
                                            {{ __('No presets available. Create one above.') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-neutral-100">
                                <button type="button" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-xs px-4 py-2 bg-primary text-base font-medium text-white hover:bg-primary-600 sm:ml-3 sm:w-auto sm:text-sm" @click="applyModalSelection()">
                                    {{ __('Add Selected') }}
                                </button>
                                <button type="button" class="mt-3 w-full inline-flex justify-center rounded-lg border border-neutral-300 shadow-xs px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="showSizeModal = false">
                                    {{ __('Cancel') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 2]) }}">{{ __('Back') }}</x-ui.button>
                    <button type="submit" class="btn btn-primary px-6 py-2.5 font-bold shadow-sm">{{ __('Save & Next: Colors →') }}</button>
                </div>
            </form>

        {{-- STEP 4: Colors (Manage + Images) --}}
        @elseif($currentStep === 4)
            <form method="POST" action="{{ route('user.commerce.products.gallery.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                <input type="hidden" name="next_step" value="5">

                <section class="rounded-2xl border border-neutral-200/80 bg-white p-6 sm:p-8 shadow-xs">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-neutral-900">{{ __('Colors') }}</h2>
                            <p class="text-xs text-neutral-500 mt-0.5">{{ __('Add unlimited colors with hex codes and assign dedicated photos for each color variation.') }}</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary text-xs shadow-2xs" @click="addColor()">
                            <i class="ph ph-plus"></i> {{ __('+ Add Color') }}
                        </button>
                    </div>

                    {{-- Colors Table matching Step 4 --}}
                    <div class="mt-5 overflow-x-auto rounded-xl border border-neutral-200 shadow-2xs">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-neutral-50/80 uppercase font-bold text-neutral-600 border-b border-neutral-200">
                                <tr>
                                    <th class="px-4 py-3">{{ __('Color Name') }}</th>
                                    <th class="px-4 py-3 w-40">{{ __('Color Code (Hex)') }}</th>
                                    <th class="px-4 py-3 w-32 text-center">{{ __('Images') }}</th>
                                    <th class="px-4 py-3 w-20 text-right">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 bg-white">
                                <template x-for="(col, cIdx) in colors" :key="col.id || cIdx">
                                    <tr class="hover:bg-neutral-50/50">
                                        {{-- Color Name --}}
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2.5">
                                                <span class="h-6 w-6 rounded-full border border-neutral-300 shadow-2xs shrink-0" :style="`background-color: ${col.hex_code || '#2563EB'}`"></span>
                                                <input type="text" class="form-input text-xs font-semibold h-9" x-model="col.name" placeholder="{{ __('Light Blue') }}">
                                            </div>
                                        </td>

                                        {{-- Color Code --}}
                                        <td class="px-4 py-3">
                                            <input type="text" class="form-input text-xs font-mono uppercase h-9" x-model="col.hex_code" placeholder="#87CEEB">
                                        </td>

                                        {{-- Color Images Thumbnail --}}
                                        <td class="px-4 py-3 min-w-[120px] text-center">
                                            <button type="button" class="btn btn-sm btn-outline text-xs px-2 !py-0 flex items-center justify-center gap-1.5 mx-auto hover:bg-primary hover:text-white hover:border-primary transition" @click="editingColorIndex = cIdx">
                                                <i class="ph ph-image-square text-sm"></i>
                                                <span x-text="getColorMediaList(cIdx).length > 0 ? getColorMediaList(cIdx).length + ' Images' : '{{ __('Add Images') }}'"></span>
                                            </button>
                                        </td>

                                        {{-- Delete Action --}}
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" class="text-neutral-400 hover:text-red-600 p-1.5 transition" @click="removeColor(cIdx)" aria-label="{{ __('Delete color') }}">
                                                <i class="ph ph-trash text-base"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Hidden inputs for Colors --}}
                    <template x-for="(col, cIdx) in colors" :key="col.id || cIdx">
                        <div>
                            <input type="hidden" :name="`colors[${cIdx}][id]`" :value="col.id || ''">
                            <input type="hidden" :name="`colors[${cIdx}][name]`" :value="col.name || ''">
                            <input type="hidden" :name="`colors[${cIdx}][hex_code]`" :value="col.hex_code || '#2563EB'">
                            <input type="hidden" :name="`colors[${cIdx}][swatch_media_id]`" :value="col.swatch_media_id || ''">
                        </div>
                    </template>

                    {{-- Hidden inputs for Media --}}
                    <template x-for="(item, index) in gallery" :key="item.id">
                        <div>
                            <input type="hidden" :name="`media[${index}][id]`" :value="item.id">
                            <input type="hidden" :name="`media[${index}][is_primary]`" :value="item.is_primary ? '1' : '0'">
                            <input type="hidden" :name="`media[${index}][alt_text]`" :value="item.alt_text || ''">
                            <input type="hidden" :name="`media[${index}][color_id]`" :value="item.color_id || ''">
                        </div>
                    </template>
                </section>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 3]) }}">{{ __('Back') }}</x-ui.button>
                    <button type="submit" class="btn btn-primary px-6 py-2.5 font-bold shadow-sm">{{ __('Save & Next: Pricing & MOQ →') }}</button>
                </div>
            </form>

        {{-- STEP 5: Pricing, MOQ & Inventory --}}
        @elseif($currentStep === 5)
            <form method="POST" action="{{ route('user.commerce.products.details.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                <input type="hidden" name="next_step" value="6">
                <input type="hidden" name="name" value="{{ $product->name }}">

                <section class="rounded-2xl border border-neutral-200/80 bg-white p-6 sm:p-8 shadow-xs">
                    <h2 class="text-lg font-bold text-neutral-900">{{ __('Pricing & Inventory') }}</h2>
                    <p class="text-xs text-neutral-500 mt-0.5">{{ __('Configure minimum order quantities, stock numbers, and optional bulk price breaks.') }}</p>

                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        {{-- Minimum Order Quantity (MOQ) --}}
                        <div>
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="moq">{{ __('Minimum Order Quantity (MOQ) *') }}</label>
                            <div class="flex items-center gap-2 mt-1">
                                <input id="moq" type="number" min="1" class="form-input text-sm font-bold flex-1" name="moq" required value="{{ old('moq', $product->moq ?? 40) }}" placeholder="40">
                                <span class="rounded-lg bg-neutral-100 px-3 py-2 text-xs font-bold text-neutral-700">Sets</span>
                            </div>
                        </div>

                        {{-- Total Stock / Inventory --}}
                        <div>
                            <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="default_stock">{{ __('Stock / Inventory *') }}</label>
                            <div class="flex items-center gap-2 mt-1">
                                <input id="default_stock" type="number" min="0" class="form-input text-sm font-bold flex-1" name="default_stock" value="{{ old('default_stock', $product->variants->isNotEmpty() ? $product->variants->first()->stock_quantity : "") }}" placeholder="500">
                                <span class="rounded-lg bg-neutral-100 px-3 py-2 text-xs font-bold text-neutral-700">Sets</span>
                            </div>
                        </div>
                    </div>

                    {{-- Price Break Rules (Optional) --}}
                    <div class="mt-8 border-t border-neutral-200 pt-5">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-800">{{ __('Price Break Rules (Optional)') }}</h3>
                                <p class="text-[11px] text-neutral-500">{{ __('Set volume discounts based on order quantity ranges.') }}</p>
                            </div>
                            <button type="button" class="btn btn-xs btn-outline text-xs shadow-2xs" @click="addTierPrice()">
                                <i class="ph ph-plus"></i> {{ __('+ Add Price Break') }}
                            </button>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(tier, tIdx) in tierPrices" :key="tIdx">
                                <div class="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_1fr_auto] items-center p-3 rounded-xl border border-neutral-200 bg-neutral-50/50">
                                    <div>
                                        <label class="text-[10px] font-bold text-neutral-600 uppercase">{{ __('Min Qty') }}</label>
                                        <input type="number" min="1" class="form-input text-xs h-9" :name="`tier_prices[${tIdx}][min_quantity]`" x-model="tier.min_quantity" placeholder="40">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-neutral-600 uppercase">{{ __('Max Qty') }}</label>
                                        <input type="number" min="1" class="form-input text-xs h-9" :name="`tier_prices[${tIdx}][max_quantity]`" x-model="tier.max_quantity" placeholder="99">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-neutral-600 uppercase">{{ __('Unit Price ($)') }}</label>
                                        <input type="number" step="0.01" min="0.01" class="form-input text-xs h-9 font-bold" :name="`tier_prices[${tIdx}][unit_price]`" x-model="tier.unit_price" placeholder="750.00">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-neutral-600 uppercase">{{ __('Discount (%)') }}</label>
                                        <input type="number" step="0.1" min="0" max="100" class="form-input text-xs h-9" :name="`tier_prices[${tIdx}][discount_percentage]`" x-model="tier.discount_percentage" placeholder="5">
                                    </div>
                                    <button type="button" class="text-neutral-400 hover:text-red-600 p-1 self-end mb-1" @click="removeTierPrice(tIdx)" aria-label="{{ __('Delete price break') }}">
                                        <i class="ph ph-trash text-base"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 4]) }}">{{ __('Back') }}</x-ui.button>
                    <button type="submit" class="btn btn-primary px-6 py-2.5 font-bold shadow-sm">{{ __('Save & Next: Feature Highlights →') }}</button>
                </div>
            </form>

        {{-- STEP 6: Feature Highlights (Icons) --}}
        @elseif($currentStep === 6)
            <form method="POST" action="{{ route('user.commerce.products.details.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                <input type="hidden" name="next_step" value="7">
                <input type="hidden" name="name" value="{{ $product->name }}">

                <section class="rounded-2xl border border-neutral-200/80 bg-white p-6 sm:p-8 shadow-xs">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-neutral-900">{{ __('Features / Highlights') }}</h2>
                            <p class="text-xs text-neutral-500 mt-0.5">{{ __('Configure the 4–5 icon badges that display directly below the main product photo.') }}</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary text-xs shadow-2xs" @click="addFeatureHighlight()">
                            <i class="ph ph-plus"></i> {{ __('+ Add Feature') }}
                        </button>
                    </div>

                    {{-- Feature Highlights List --}}
                    <div class="mt-5 space-y-3">
                        <template x-for="(feat, fIdx) in featureHighlights" :key="fIdx">
                            <div class="flex items-center gap-3 p-3 rounded-xl border border-neutral-200 bg-white shadow-2xs">
                                <div class="h-10 w-10 rounded-full bg-neutral-100 flex items-center justify-center text-neutral-800 shrink-0">
                                    <i class="ph text-xl" :class="feat.icon || 'ph-check-circle'"></i>
                                </div>
                                <div class="w-44">
                                    <select class="form-input text-xs py-2.5" x-model="feat.icon">
                                        <option value="ph-t-shirt">{{ __('T-Shirt / Fleece') }}</option>
                                        <option value="ph-arrows-out-line-vertical">{{ __('Zipper / Set') }}</option>
                                        <option value="ph-users-three">{{ __('Users / Kids') }}</option>
                                        <option value="ph-palette">{{ __('Palette / Colors') }}</option>
                                        <option value="ph-ruler">{{ __('Ruler / True Size') }}</option>
                                        <option value="ph-seal-check">{{ __('Quality Seal') }}</option>
                                        <option value="ph-buildings">{{ __('Factory Direct') }}</option>
                                        <option value="ph-globe">{{ __('Worldwide') }}</option>
                                        <option value="ph-lock-key">{{ __('Secure') }}</option>
                                    </select>
                                </div>
                                <input
                                    type="text"
                                    class="form-input text-xs font-bold py-2.5 flex-1"
                                    x-model="feat.label"
                                    placeholder="{{ __('Premium Tech Fleece') }}"
                                    required
                                >
                                <button type="button" class="text-neutral-400 hover:text-red-600 p-1.5 transition" @click="removeFeatureHighlight(fIdx)" aria-label="{{ __('Delete feature') }}">
                                    <i class="ph ph-trash text-base"></i>
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Hidden inputs --}}
                    <template x-for="(feat, fIdx) in featureHighlights" :key="fIdx">
                        <div>
                            <input type="hidden" :name="`feature_highlights[${fIdx}][icon]`" :value="feat.icon">
                            <input type="hidden" :name="`feature_highlights[${fIdx}][label]`" :value="feat.label">
                        </div>
                    </template>
                </section>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 5]) }}">{{ __('Back') }}</x-ui.button>
                    <button type="submit" class="btn btn-primary px-6 py-2.5 font-bold shadow-sm">{{ __('Save & Next: Description →') }}</button>
                </div>
            </form>

        {{-- STEP 7: Description & Details --}}
        @elseif($currentStep === 7)
            <form method="POST" action="{{ route('user.commerce.products.details.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                <input type="hidden" name="next_step" value="8">
                <input type="hidden" name="name" value="{{ $product->name }}">

                <section class="rounded-2xl border border-neutral-200/80 bg-white p-6 sm:p-8 shadow-xs">
                    <h2 class="text-lg font-bold text-neutral-900">{{ __('Product Description') }}</h2>
                    <p class="text-xs text-neutral-500 mt-0.5">{{ __('Provide the full marketing description, fabric details, and bullet-point feature checklist.') }}</p>

                    <div class="mt-6 space-y-5">
                        {{-- Full Description --}}
                        <div class="col-span-full">
                            <x-forms.editor name="description" label="{{ __('Full Description') }}" :value="old('description', $product->description)" placeholder="{{ __('Premium Kids Nike Tech Fleece Full-Zip Tracksuit designed for everyday comfort and a stylish sporty look.') }}" />
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 6]) }}">{{ __('Back') }}</x-ui.button>
                    <button type="submit" class="btn btn-primary px-6 py-2.5 font-bold shadow-sm">{{ __('Save & Next: Specifications →') }}</button>
                </div>
            </form>

        {{-- STEP 8: Specifications & Save --}}
        @elseif($currentStep === 8)
            <form method="POST" action="{{ route('user.commerce.products.details.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                <input type="hidden" name="next_step" value="8">
                <input type="hidden" name="name" value="{{ $product->name }}">

                <section class="rounded-2xl border border-neutral-200/80 bg-white p-6 sm:p-8 shadow-xs">
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-neutral-900">{{ __('Specifications') }}</h2>
                            <p class="text-xs text-neutral-500 mt-0.5">{{ __('Review and edit the technical specifications table for the storefront.') }}</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline text-xs shadow-2xs" @click="addSpecification()">
                            <i class="ph ph-plus"></i> {{ __('+ Add Specification') }}
                        </button>
                    </div>

                    {{-- Specifications Table matching Step 9 --}}
                    <div class="mt-5 rounded-xl border border-neutral-200 overflow-hidden shadow-2xs">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-neutral-50/80 uppercase font-bold text-neutral-600 border-b border-neutral-200">
                                <tr>
                                    <th class="px-4 py-3 w-1/3">{{ __('Attribute') }}</th>
                                    <th class="px-4 py-3">{{ __('Value') }}</th>
                                    <th class="px-4 py-3 w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 bg-white">
                                <template x-for="(spec, sIdx) in specifications" :key="sIdx">
                                    <tr class="hover:bg-neutral-50/50">
                                        <td class="px-4 py-2.5">
                                            <input type="text" class="form-input text-xs font-bold h-9" x-model="spec.attribute" placeholder="{{ __('Material') }}" required>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <input type="text" class="form-input text-xs h-9" x-model="spec.value" placeholder="{{ __('Tech Fleece (Premium Quality)') }}" required>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <button type="button" class="text-neutral-400 hover:text-red-600 p-1" @click="removeSpecification(sIdx)">
                                                <i class="ph ph-trash text-sm"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Hidden inputs for specs --}}
                    <template x-for="(spec, sIdx) in specifications" :key="sIdx">
                        <div>
                            <input type="hidden" :name="`specifications[${sIdx}][attribute]`" :value="spec.attribute">
                            <input type="hidden" :name="`specifications[${sIdx}][value]`" :value="spec.value">
                        </div>
                    </template>

                    {{-- Hidden inputs for Material, Fit, Gender, Season fields sync --}}
                    <input type="hidden" name="material" :value="(specifications.find(s => s.attribute.toLowerCase() === 'material') || {}).value || '{{ $product->material }}'">
                    <input type="hidden" name="fit" :value="(specifications.find(s => s.attribute.toLowerCase() === 'fit') || {}).value || '{{ $product->fit }}'">
                    <input type="hidden" name="set_includes" :value="(specifications.find(s => s.attribute.toLowerCase().includes('include')) || {}).value || '{{ $product->set_includes }}'">
                    <input type="hidden" name="gender" :value="(specifications.find(s => s.attribute.toLowerCase() === 'gender') || {}).value || '{{ $product->gender }}'">
                    <input type="hidden" name="season" :value="(specifications.find(s => s.attribute.toLowerCase() === 'season') || {}).value || '{{ $product->season }}'">
                </section>

                {{-- Final Action Buttons matching Screenshot --}}
                <div class="flex items-center justify-end gap-3 pt-2">
                    <x-ui.button variant="outline" href="{{ route('user.commerce.products.index') }}">{{ __('Cancel') }}</x-ui.button>
                    <button
                        type="submit"
                        name="status"
                        value="draft"
                        class="btn btn-outline text-xs"
                    >
                        {{ __('Save as Draft') }}
                    </button>
                    <button
                        type="submit"
                        name="status"
                        value="active"
                        class="btn btn-primary text-xs flex items-center gap-1.5 px-5 py-2.5 font-bold shadow-sm"
                    >
                        <i class="ph ph-floppy-disk text-base"></i>
                        <span>{{ __('Save Product') }}</span>
                </div>
            </form>
        @endif

        {{-- Color Images Management Modal --}}
        <template x-if="editingColorIndex !== null">
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden bg-neutral-900/50 p-4 backdrop-blur-sm" @keydown.escape.window="editingColorIndex = null" @click.self="editingColorIndex = null">
                <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-xl flex flex-col">
                    {{-- Modal Header --}}
                    <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-4">
                        <h3 class="text-lg font-bold text-neutral-900 flex items-center gap-2">
                            <span class="h-4 w-4 rounded-full border shadow-2xs" :style="`background-color: ${colors[editingColorIndex].hex_code || '#2563EB'}`"></span>
                            <span>{{ __('Manage Images for ') }}<span x-text="colors[editingColorIndex].name || 'Color'"></span></span>
                        </h3>
                        <button type="button" class="text-neutral-400 hover:text-neutral-600 transition" @click="editingColorIndex = null">
                            <i class="ph ph-x text-xl"></i>
                        </button>
                    </div>

                    {{-- Modal Body --}}
                    <div class="px-6 py-6 overflow-y-auto max-h-[60vh]">
                        <p class="text-xs text-neutral-500 mb-4">{{ __('Upload and select images specifically for this color variant. Click an image to set it as the primary swatch for this color.') }}</p>
                        
                        <div class="flex flex-wrap gap-4">
                            {{-- Add New Image Button --}}
                            <button type="button" class="h-24 w-24 rounded-xl border-2 border-dashed border-neutral-300 hover:border-primary bg-neutral-50 flex flex-col items-center justify-center text-neutral-500 hover:text-primary transition shadow-2xs cursor-pointer" @click="openMediaPickerForColor(editingColorIndex)">
                                <i class="ph ph-plus text-2xl mb-1"></i>
                                <span class="text-[10px] font-semibold uppercase tracking-wider">{{ __('Add') }}</span>
                            </button>

                            {{-- Gallery Grid --}}
                            <template x-for="(cMedia, cmIdx) in getColorMediaList(editingColorIndex)" :key="cMedia.id">
                                <div class="relative group h-24 w-24">
                                    <img 
                                        :src="cMedia.url" 
                                        class="h-full w-full rounded-xl object-cover border-2 shadow-2xs cursor-pointer transition" 
                                        :class="colors[editingColorIndex].swatch_media_id == cMedia.id ? 'border-primary ring-2 ring-primary ring-offset-2' : 'border-neutral-200 hover:border-neutral-400'" 
                                        @click="setColorPrimary(editingColorIndex, cMedia.id)" 
                                        :title="colors[editingColorIndex].swatch_media_id == cMedia.id ? 'Primary Swatch' : 'Click to Set as Swatch'"
                                    >
                                    
                                    {{-- Primary Badge --}}
                                    <template x-if="colors[editingColorIndex].swatch_media_id == cMedia.id">
                                        <div class="absolute -bottom-2 -left-2 bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm z-10 border border-white">
                                            {{ __('PRIMARY') }}
                                        </div>
                                    </template>

                                    {{-- Remove Action --}}
                                    <button type="button" class="absolute -top-2 -right-2 bg-white text-red-600 hover:bg-red-600 hover:text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition shadow-md z-10 border border-neutral-200" @click.stop="removeMediaFromColor(cMedia.id, editingColorIndex)" title="Remove Image">
                                        <i class="ph ph-trash text-xs"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="border-t border-neutral-100 px-6 py-4 flex justify-end">
                        <button type="button" class="btn btn-primary" @click="editingColorIndex = null">{{ __('Done') }}</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts.user>
