@php
    $isEdit = isset($product);
    $galleryState = $product?->gallery?->map(fn ($item) => ['id' => $item->media_id, 'color_id' => $item->color_id ? (string) $item->color_id : null, 'name' => $item->media?->name, 'url' => $item->media?->url, 'type' => $item->media_type, 'alt_text' => $item->alt_text, 'is_primary' => $item->is_primary])->values()->all() ?? [];
    $colorState = $product?->colors?->map(fn ($c) => ['id' => $c->id, 'name' => $c->name ?? '', 'hex_code' => $c->hex_code ?: '#2563EB', 'color_family' => $c->color_family ?? '', 'swatch_media_id' => $c->swatch_media_id])->values()->all() ?? [
        ['id' => '', 'name' => 'Royal Blue', 'hex_code' => '#1E3A8A', 'color_family' => 'Blue', 'swatch_media_id' => ''],
        ['id' => '', 'name' => 'Jet Black', 'hex_code' => '#111827', 'color_family' => 'Black', 'swatch_media_id' => ''],
    ];
    $sizeOption = $product?->options?->first(fn ($o) => strtolower($o->code ?? '') === 'size' || strtolower($o->name ?? '') === 'size');
    $sizeState = $sizeOption ? $sizeOption->values->pluck('value')->all() : ($product?->variants?->pluck('size')->filter()->unique()->values()->all() ?: ['S', 'M', 'L', 'XL', 'XXL']);
    $steps = [
        1 => ['label' => __('Basic Info & Specs'), 'icon' => 'ph-note-pencil'],
        2 => ['label' => __('Colors & Sizes'), 'icon' => 'ph-palette'],
        3 => ['label' => __('Color Photos & Gallery'), 'icon' => 'ph-images'],
        4 => ['label' => __('Stock Matrix (Color → Size)'), 'icon' => 'ph-squares-four'],
        5 => ['label' => __('Review & Publish'), 'icon' => 'ph-check-circle'],
    ];
@endphp

<x-layouts.user :title="$isEdit ? __('Edit garment product') : __('Create garment product')">
    <div
        class="mx-auto max-w-7xl space-y-6"
        x-data="commerceProductWizard(@js(['gallery' => $galleryState, 'colors' => $colorState, 'sizes' => $sizeState, 'variants' => $variantPreview, 'previewUrl' => $isEdit ? route('user.commerce.products.variants.preview', $product) : null]))"
        @change="dirty = true"
        @media-picker:selected="addMedia($event.detail.media)"
    >
        <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary">{{ __('Garment Catalog Product') }}</p>
                <h1 class="heading-3 text-title">{{ $isEdit ? $product->name : __('Create garment product') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Setup single pieces, bulk wholesale price, dedicated color photos, and Color → Size → Count inventory matrix.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($isEdit && $product->status === 'active')
                    <x-ui.button variant="outline" href="{{ route('commerce.products.public', ['workspace' => $product->workspace->slug, 'product' => $product->slug]) }}" target="_blank"><i class="ph ph-arrow-square-out"></i> {{ __('Preview Storefront') }}</x-ui.button>
                @endif
                <x-ui.button variant="outline" href="{{ route('user.commerce.products.index') }}">{{ __('Save and exit') }}</x-ui.button>
            </div>
        </header>

        @include('commerce::user.partials.help', ['helpKey' => 'product_form'])

        <nav class="overflow-x-auto rounded-2xl bg-neutral-0 p-4" aria-label="{{ __('Product setup progress') }}">
            <ol class="grid min-w-[680px] grid-cols-5 text-sm">
                @foreach($steps as $number => $stepMeta)
                    @php
                        $isCurrentStep = $step === $number;
                        $isCompletedStep = $isEdit && $product->wizard_step > $number;
                        $isReachableStep = $isEdit && $number <= max($product->wizard_step, $step);
                    @endphp
                    <li class="group relative">
                        @if (! $loop->last)
                            <span class="pointer-events-none absolute left-[calc(50%+1.25rem)] right-[calc(-50%+1.25rem)] top-5 z-0 h-px {{ $isEdit && $product->wizard_step > $number ? 'bg-primary' : 'bg-primary/25' }}" aria-hidden="true"></span>
                        @endif

                        @if($isReachableStep)
                            <a href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => $number]) }}" class="relative z-10 flex flex-col items-center gap-2 rounded-xl px-2 py-1 text-center font-semibold text-primary transition hover:text-primary/80" @if($isCurrentStep) aria-current="step" @endif>
                                <span class="grid h-10 w-10 place-items-center rounded-full border transition {{ $isCurrentStep ? 'border-primary bg-primary text-neutral-0 shadow-sm shadow-primary/20' : ($isCompletedStep ? 'border-primary bg-primary/10 text-primary' : 'border-primary/40 bg-primary/5 text-primary') }}">
                                    <i class="ph {{ $isCompletedStep ? 'ph-check' : $stepMeta['icon'] }} text-lg"></i>
                                </span>
                                <span>{{ $stepMeta['label'] }}</span>
                            </a>
                        @else
                            <span class="relative z-10 flex flex-col items-center gap-2 rounded-xl px-2 py-1 text-center font-semibold {{ $isCurrentStep ? 'text-primary' : 'text-primary/50' }}" @if($isCurrentStep) aria-current="step" @endif>
                                <span class="grid h-10 w-10 place-items-center rounded-full border {{ $isCurrentStep ? 'border-primary bg-primary text-neutral-0 shadow-sm shadow-primary/20' : 'border-primary/30 bg-primary/5 text-primary/50' }}">
                                    <i class="ph {{ $stepMeta['icon'] }} text-lg"></i>
                                </span>
                                <span>{{ $stepMeta['label'] }}</span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>

        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error" role="alert"><p class="font-semibold">{{ __('Please fix the highlighted information.') }}</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        {{-- STEP 1: Basic Details, Pricing & Garment Specs --}}
        @if($step === 1)
            <form method="POST" action="{{ $isEdit ? route('user.commerce.products.details.update', $product) : route('user.commerce.products.store') }}" class="space-y-6" @submit="markSaved()">
                @csrf @if($isEdit) @method('PUT') @endif
                <section class="section-card">
                    <div>
                        <h2 class="heading-5 text-title">{{ __('Garment basic details & pricing') }}</h2>
                        <p class="text-sm text-body">{{ __('Set base garment specs, single sample piece price, wholesale bulk price, and fabric composition.') }}</p>
                    </div>
                    
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="form-label" for="name">{{ __('Product name') }}</label>
                            <input id="name" class="form-input" name="name" required maxlength="255" value="{{ old('name', $product?->name) }}" placeholder="{{ __('e.g. 180 GSM Heavyweight Cotton T-Shirt') }}">
                        </div>
                        <div>
                            <label class="form-label" for="category_id">{{ __('Category') }}</label>
                            <select id="category_id" class="form-input" name="category_id">
                                <option value="">{{ __('Select category') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_id', $product?->category_id) == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <label class="form-label" for="brand_id">{{ __('Brand') }}</label>
                                <a class="text-xs font-semibold text-primary hover:underline" href="{{ route('user.commerce.brands.index') }}">{{ __('Manage brands') }}</a>
                            </div>
                            <select id="brand_id" class="form-input" name="brand_id">
                                <option value="">{{ __('Select brand') }}</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected(old('brand_id', $product?->brand_id) == $brand->id)>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <label class="form-label" for="audience_id">{{ __('Audience') }}</label>
                                <a class="text-xs font-semibold text-primary hover:underline" href="{{ route('user.commerce.audiences.index') }}">{{ __('Manage audiences') }}</a>
                            </div>
                            <select id="audience_id" class="form-input" name="audience_id">
                                <option value="">{{ __('Select audience') }}</option>
                                @foreach($audiences as $audience)
                                    <option value="{{ $audience->id }}" @selected(old('audience_id', $product?->audience_id) == $audience->id)>{{ $audience->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Direct Two-Price Inputs --}}
                        <div class="rounded-xl border border-border/80 bg-neutral-50/50 p-4">
                            <label class="form-label text-primary font-semibold" for="single_piece_price">{{ __('1. Single sample sale price (USD)') }}</label>
                            <input id="single_piece_price" type="number" step="0.01" min="0.01" class="form-input font-bold" name="single_piece_price" value="{{ old('single_piece_price', $product?->single_piece_price ?? 9.00) }}" placeholder="9.00">
                            <span class="text-xs text-body">{{ __('Sample purchase price for single pieces (e.g. $9.00 USD).') }}</span>
                        </div>
                        <div class="rounded-xl border border-emerald-500/30 bg-emerald-50/30 p-4">
                            <label class="form-label text-emerald-800 font-semibold" for="wholesale_price">{{ __('2. Wholesale bulk price (USD)') }}</label>
                            <input id="wholesale_price" type="number" step="0.01" min="0.01" class="form-input font-bold text-emerald-700" name="wholesale_price" value="{{ old('wholesale_price', $product?->wholesale_price ?? 6.50) }}" placeholder="6.50">
                            <span class="text-xs text-emerald-700">{{ __('Bulk wholesale export price per piece (e.g. $6.50 USD).') }}</span>
                        </div>

                        <div>
                            <label class="form-label" for="default_unit_weight_kg">{{ __('Weight per piece (kg)') }}</label>
                            <input id="default_unit_weight_kg" type="number" step="0.001" min="0.001" class="form-input" name="default_unit_weight_kg" value="{{ old('default_unit_weight_kg', $product?->default_unit_weight_kg ?? 0.030) }}" placeholder="0.030">
                            <span class="text-xs text-body">{{ __('Used for dynamic international air parcel & bulk freight calculation.') }}</span>
                        </div>
                        <div>
                            <label class="form-label" for="fabric_gsm">{{ __('Fabric GSM / Density') }}</label>
                            <input id="fabric_gsm" class="form-input" name="fabric_gsm" value="{{ old('fabric_gsm', $product?->fabric_gsm) }}" placeholder="{{ __('e.g. 180 GSM, 240 GSM French Terry') }}">
                        </div>
                        <div>
                            <label class="form-label" for="material">{{ __('Fabric material composition') }}</label>
                            <input id="material" class="form-input" name="material" value="{{ old('material', $product?->material) }}" placeholder="{{ __('e.g. 100% Combed Compact Cotton') }}">
                        </div>
                        <div>
                            <label class="form-label" for="condition">{{ __('Condition') }}</label>
                            <select id="condition" class="form-input" name="condition">
                                <option value="new" @selected(old('condition', $product?->condition ?? 'new') === 'new')>{{ __('New') }}</option>
                                <option value="refurbished" @selected(old('condition', $product?->condition) === 'refurbished')>{{ __('Refurbished') }}</option>
                                <option value="used" @selected(old('condition', $product?->condition) === 'used')>{{ __('Used') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="country_of_origin">{{ __('Country of origin') }}</label>
                            <input id="country_of_origin" class="form-input" name="country_of_origin" maxlength="2" value="{{ old('country_of_origin', $product?->country_of_origin ?? 'BD') }}">
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="description">{{ __('Description') }}</label>
                            <textarea id="description" class="form-input min-h-32" name="description" maxlength="5000" placeholder="{{ __('Describe the fabric weave, stitching gauge, cut, feel, and garment quality.') }}">{{ old('description', $product?->description) }}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="care_information">{{ __('Care instructions') }}</label>
                            <textarea id="care_information" class="form-input min-h-24" name="care_information" maxlength="2000" placeholder="{{ __('Machine wash warm (40°C) with like colors, tumble dry medium...') }}">{{ old('care_information', $product?->care_information) }}</textarea>
                        </div>
                    </div>
                </section>
                <div class="sticky bottom-4 flex justify-end rounded-2xl bg-neutral-0/95 p-3 shadow-lg backdrop-blur">
                    <x-forms.submit :label="$isEdit ? __('Save and configure colors') : __('Create draft and configure colors')" />
                </div>
            </form>

        {{-- STEP 2: Colors & Sizes Setup --}}
        @elseif($step === 2)
            <form method="POST" action="{{ route('user.commerce.products.options.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                
                {{-- Garment Colors Section --}}
                <section class="section-card">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="heading-5 text-title">{{ __('1. Garment color swatches') }}</h2>
                            <p class="text-sm text-body">{{ __('Add garment colorways using the HEX color picker. You can leave the name blank if unknown and it will fallback to the visual swatch.') }}</p>
                        </div>
                        <button type="button" class="btn btn-outline" @click="addColor()">
                            <i class="ph ph-plus"></i> {{ __('Add color swatch') }}
                        </button>
                    </div>

                    <div class="mt-5 space-y-3">
                        <template x-for="(col, cIndex) in colors" :key="cIndex">
                            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-border bg-neutral-0 p-4">
                                <input type="hidden" :name="`colors[${cIndex}][id]`" :value="col.id || ''">
                                <div class="flex items-center gap-2">
                                    <input type="color" class="h-10 w-12 cursor-pointer rounded-lg border border-border bg-transparent p-1" :name="`colors[${cIndex}][hex_code]`" x-model="col.hex_code">
                                    <span class="font-mono text-xs text-body" x-text="col.hex_code"></span>
                                </div>
                                <div class="flex-1 min-w-[140px]">
                                    <input class="form-input" :name="`colors[${cIndex}][name]`" x-model="col.name" placeholder="{{ __('Color name, e.g. Royal Blue (optional)') }}">
                                </div>
                                <div class="w-36">
                                    <select class="form-input" :name="`colors[${cIndex}][color_family]`" x-model="col.color_family">
                                        <option value="">{{ __('Color family') }}</option>
                                        <option value="Blue">{{ __('Blue') }}</option>
                                        <option value="Black">{{ __('Black') }}</option>
                                        <option value="White">{{ __('White') }}</option>
                                        <option value="Red">{{ __('Red') }}</option>
                                        <option value="Green">{{ __('Green') }}</option>
                                        <option value="Grey">{{ __('Grey') }}</option>
                                        <option value="Yellow">{{ __('Yellow') }}</option>
                                        <option value="Earth">{{ __('Earth / Brown') }}</option>
                                        <option value="Pastel">{{ __('Pastel') }}</option>
                                    </select>
                                </div>
                                <button type="button" class="row-action text-error" @click="removeColor(cIndex)" aria-label="{{ __('Remove color') }}">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </div>
                        </template>

                        <div x-show="colors.length === 0" class="rounded-2xl border border-dashed border-border p-6 text-center text-body">
                            {{ __('No custom color swatches added yet. Click "Add color swatch" above.') }}
                        </div>
                    </div>
                </section>

                {{-- Garment Sizes & Reusable Presets --}}
                <section class="section-card">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="heading-5 text-title">{{ __('2. Garment sizes & size presets') }}</h2>
                            <p class="text-sm text-body">{{ __('Choose a saved size preset or click individual size badges below.') }}</p>
                        </div>
                        <a href="{{ route('user.commerce.variants.index') }}" target="_blank" class="btn btn-sm btn-outline text-xs">
                            <i class="ph ph-sliders"></i> {{ __('Manage Size Presets') }}
                        </a>
                    </div>

                    {{-- Reusable Preset Quick Selector --}}
                    @if(isset($variantPresets) && $variantPresets->isNotEmpty())
                        <div class="mt-5 rounded-2xl border border-primary/20 bg-primary/5 p-4">
                            <div class="flex items-center gap-2 mb-2.5">
                                <i class="ph ph-lightning text-primary font-bold"></i>
                                <span class="text-xs font-bold uppercase tracking-wider text-primary">{{ __('1-Click Apply Saved Preset') }}</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($variantPresets as $preset)
                                    <button
                                        type="button"
                                        class="group flex items-center gap-2 rounded-xl border border-primary/30 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-800 shadow-xs transition hover:border-primary hover:bg-primary/10 hover:text-primary"
                                        @click="applyPreset(@js($preset->values))"
                                    >
                                        <span>{{ $preset->name }}</span>
                                        <span class="text-[10px] text-neutral-400 font-normal group-hover:text-primary">({{ implode(', ', $preset->values) }})</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Quick Size Clickable Badges --}}
                    <div class="mt-5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-neutral-600 mb-2.5">{{ __('Quick Size Toggle') }}</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL'] as $quickSize)
                                <button
                                    type="button"
                                    class="h-10 min-w-12 rounded-xl border font-bold text-xs transition-all shadow-xs"
                                    :class="hasSize('{{ $quickSize }}') ? 'border-primary bg-primary text-white ring-2 ring-primary/30' : 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:bg-neutral-50'"
                                    @click="toggleSize('{{ $quickSize }}')"
                                >
                                    {{ $quickSize }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Active Selected Sizes list & Custom Size Input --}}
                    <div class="mt-6 border-t border-border pt-4">
                        <label class="block text-xs font-bold uppercase tracking-wider text-neutral-600 mb-2.5">{{ __('Selected Sizes for this Product') }}</label>
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            <template x-for="(sz, sIdx) in sizes" :key="sIdx">
                                <span class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-300 bg-neutral-900 px-3 py-1.5 text-xs font-bold text-white shadow-xs">
                                    <span x-text="sz"></span>
                                    <button type="button" class="text-neutral-400 hover:text-red-400" @click="removeSize(sIdx)" aria-label="{{ __('Remove size') }}">
                                        <i class="ph ph-x"></i>
                                    </button>
                                </span>
                            </template>
                            <span x-show="sizes.length === 0" class="text-xs text-error font-medium">{{ __('Please select at least one size.') }}</span>
                        </div>

                        {{-- Add Custom Size Box --}}
                        <div class="flex items-center gap-2 max-w-md">
                            <input
                                type="text"
                                class="form-input text-xs"
                                x-model="customSize"
                                @keydown.enter.prevent="addCustomSize()"
                                placeholder="{{ __('Add custom sizes (e.g. 6Y, 8Y, 28W, 30W)...') }}"
                            >
                            <button type="button" class="btn btn-sm btn-outline text-xs shrink-0" @click="addCustomSize()">
                                <i class="ph ph-plus"></i> {{ __('Add Size') }}
                            </button>
                        </div>

                        {{-- Hidden Inputs to submit sizes --}}
                        <template x-for="(sz, sIdx) in sizes" :key="'h-'+sIdx">
                            <input type="hidden" name="sizes[]" :value="sz">
                        </template>
                    </div>
                </section>

                <div class="sticky bottom-4 flex justify-between gap-3 rounded-2xl bg-neutral-0/95 p-3 shadow-lg backdrop-blur">
                    <x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 1]) }}">{{ __('Back') }}</x-ui.button>
                    <x-forms.submit :label="__('Save and upload color photos')" />
                </div>
            </form>

        {{-- STEP 3: Dedicated Color Galleries & Lookbook --}}
        @elseif($step === 3)
            <form method="POST" action="{{ route('user.commerce.products.gallery.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                
                {{-- Section 1: Colorway Galleries (Multi-Image per Color) --}}
                <div class="space-y-6">
                    <template x-for="(col, cIndex) in colors" :key="cIndex">
                        <section class="section-card border-2 transition hover:border-primary/40">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border pb-4">
                                <div class="flex items-center gap-3">
                                    <span class="inline-block h-7 w-7 rounded-full border-2 border-white shadow-md" :style="`background-color: ${col.hex_code || '#2563EB'}`"></span>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-base font-bold text-title" x-text="col.name || col.hex_code"></h3>
                                            <span class="badge badge-soft text-xs" x-text="col.color_family || 'Colorway'"></span>
                                        </div>
                                        <p class="text-xs text-body mt-0.5" x-text="`${getColorMediaList(cIndex).length} photos assigned to this color`"></p>
                                    </div>
                                </div>

                                {{-- Button to Open Media Picker for this specific color --}}
                                <div class="flex items-center gap-2">
                                    <x-media.picker
                                        name="color_gallery_picker"
                                        accept="image"
                                        :multiple="true"
                                        :label="__('+ Add Images to this Color')"
                                        @media-picker:selected="addMediaToColor(cIndex, $event.detail.media)"
                                    />
                                </div>
                            </div>

                            {{-- Multi-Image Grid for this Color --}}
                            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                <template x-for="item in getColorMediaList(cIndex)" :key="item.id">
                                    <article class="overflow-hidden rounded-2xl border border-border bg-neutral-50 shadow-2xs group relative transition hover:shadow-md">
                                        <div class="aspect-[4/3] bg-neutral-200 relative overflow-hidden">
                                            <img :src="item.url" :alt="item.alt_text || col.name" class="h-full w-full object-cover">
                                            
                                            {{-- Badges --}}
                                            <div class="absolute top-2 left-2 flex flex-col gap-1">
                                                <span x-show="col.swatch_media_id == item.id" class="rounded-lg bg-emerald-600 px-2 py-0.5 text-[11px] font-bold text-white shadow-xs">
                                                    <i class="ph ph-star-fill"></i> {{ __('Color Hero') }}
                                                </span>
                                                <span x-show="item.is_primary" class="rounded-lg bg-primary px-2 py-0.5 text-[11px] font-bold text-white shadow-xs">
                                                    {{ __('Product Cover') }}
                                                </span>
                                            </div>

                                            <button
                                                type="button"
                                                class="absolute top-2 right-2 h-7 w-7 rounded-full bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-error"
                                                @click="removeMediaById(item.id)"
                                                aria-label="{{ __('Remove image') }}"
                                            >
                                                <i class="ph ph-trash text-sm"></i>
                                            </button>
                                        </div>

                                        <div class="p-3 space-y-2 bg-white">
                                            <input class="form-input text-xs" x-model="item.alt_text" maxlength="255" placeholder="{{ __('Photo caption / angle...') }}">
                                            
                                            <div class="flex items-center justify-between gap-1.5 pt-1">
                                                <button
                                                    type="button"
                                                    class="btn btn-xs flex-1 text-[11px]"
                                                    :class="col.swatch_media_id == item.id ? 'btn-primary' : 'btn-outline'"
                                                    @click="setColorPrimary(cIndex, item.id)"
                                                >
                                                    <i class="ph" :class="col.swatch_media_id == item.id ? 'ph-check-circle' : 'ph-star'"></i>
                                                    <span x-text="col.swatch_media_id == item.id ? '{{ __('Main Color Hero') }}' : '{{ __('Make Color Hero') }}'"></span>
                                                </button>
                                                
                                                <button
                                                    type="button"
                                                    class="btn btn-xs btn-outline text-[11px]"
                                                    x-show="!item.is_primary"
                                                    @click="setPrimaryById(item.id)"
                                                    title="{{ __('Set as main product catalog cover') }}"
                                                >
                                                    {{ __('Product Cover') }}
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                </template>

                                {{-- Empty State for this Color --}}
                                <div x-show="getColorMediaList(cIndex).length === 0" class="col-span-full rounded-2xl border-2 border-dashed border-neutral-200 bg-neutral-50/50 p-6 text-center text-body">
                                    <i class="ph ph-images text-3xl text-neutral-400"></i>
                                    <p class="mt-1 font-semibold text-sm text-title" x-text="`No photos added for ${col.name || col.hex_code} yet`"></p>
                                    <p class="text-xs text-neutral-500 mt-0.5">{{ __('Click \"+ Add Images to this Color\" to upload front, back, and detail shots in this color.') }}</p>
                                </div>
                            </div>
                        </section>
                    </template>

                    <div x-show="colors.length === 0" class="rounded-2xl border border-dashed border-border p-6 text-center text-body">
                        {{ __('No colors defined. Return to Step 2 to add color swatches.') }}
                    </div>
                </div>

                {{-- Section 2: General Lookbook & Video Showcase --}}
                <section class="section-card">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="heading-5 text-title">{{ __('General Lookbook Gallery & Video Showcase') }}</h2>
                            <p class="text-sm text-body">{{ __('Upload group model shots, flatlays, fabric closeups, and video showcase.') }}</p>
                        </div>
                        <x-media.picker name="general_gallery_picker" accept="all" :multiple="true" :label="__('+ Add Lookbook Media / Video')" @media-picker:selected="addMedia($event.detail.media, null)" />
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <template x-for="item in getGeneralMediaList()" :key="item.id">
                            <article class="overflow-hidden rounded-2xl border border-border bg-neutral-0">
                                <div class="aspect-[4/3] bg-neutral-100 relative">
                                    <template x-if="item.type === 'image'"><img :src="item.url" :alt="item.alt_text" class="h-full w-full object-cover"></template>
                                    <template x-if="item.type === 'video'"><video :src="item.url" controls preload="metadata" class="h-full w-full object-cover"></video></template>
                                    
                                    <button
                                        type="button"
                                        class="absolute top-2 right-2 h-7 w-7 rounded-full bg-black/60 text-white flex items-center justify-center hover:bg-error"
                                        @click="removeMediaById(item.id)"
                                        aria-label="{{ __('Remove media') }}"
                                    >
                                        <i class="ph ph-trash text-sm"></i>
                                    </button>
                                </div>
                                <div class="space-y-2 p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="badge badge-soft text-xs" x-text="item.type"></span>
                                        <span x-show="item.is_primary" class="badge bg-primary/10 text-primary text-xs">{{ __('Product Cover') }}</span>
                                    </div>
                                    <input class="form-input text-xs" x-model="item.alt_text" maxlength="255" placeholder="{{ __('Photo caption') }}">
                                    <button type="button" class="btn btn-xs btn-outline w-full text-xs" x-show="item.type === 'image' && !item.is_primary" @click="setPrimaryById(item.id)">{{ __('Make Product Cover') }}</button>
                                </div>
                            </article>
                        </template>
                        <div x-show="getGeneralMediaList().length === 0" class="col-span-full rounded-2xl border border-dashed border-border p-6 text-center text-body">
                            <i class="ph ph-film-strip text-3xl text-neutral-300"></i>
                            <p class="mt-1 font-semibold text-xs text-title">{{ __('No general lookbook media or video added') }}</p>
                            <p class="text-[11px] text-neutral-400">{{ __('Optional: Add overall lifestyle photos or MP4 video demo.') }}</p>
                        </div>
                    </div>
                </section>

                {{-- Hidden Form Fields for Form Submission --}}
                <template x-for="(item, index) in gallery" :key="'g-' + item.id">
                    <div>
                        <input type="hidden" :name="`media[${index}][id]`" :value="item.id">
                        <input type="hidden" :name="`media[${index}][color_id]`" :value="item.color_id || ''">
                        <input type="hidden" :name="`media[${index}][alt_text]`" :value="item.alt_text || ''">
                        <input type="hidden" :name="`media[${index}][is_primary]`" :value="item.is_primary ? 1 : 0">
                    </div>
                </template>
                <template x-for="(col, cIndex) in colors" :key="'c-' + cIndex">
                    <div>
                        <input type="hidden" :name="`colors[${cIndex}][id]`" :value="col.id || ''">
                        <input type="hidden" :name="`colors[${cIndex}][swatch_media_id]`" :value="col.swatch_media_id || ''">
                    </div>
                </template>

                <div class="sticky bottom-4 flex justify-between gap-3 rounded-2xl bg-neutral-0/95 p-3 shadow-lg backdrop-blur">
                    <x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 2]) }}">{{ __('Back') }}</x-ui.button>
                    <x-forms.submit :label="__('Save photos and set stock matrix')" />
                </div>
            </form>

        {{-- STEP 4: Color → Size → Count Variant Inventory Matrix --}}
        @elseif($step === 4)
            <form method="POST" action="{{ route('user.commerce.products.variants.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')

                <section class="section-card">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex items-center gap-3">
                                <h2 class="heading-5 text-title">{{ __('Variant stock matrix (Color → Size → Count)') }}</h2>
                                <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">
                                    {{ __('Total Stock: ') }} <span x-text="getTotalStock()"></span> {{ __('pcs') }}
                                </span>
                            </div>
                            <p class="text-sm text-body mt-1">{{ __('Manage inventory stock count for every size grouped by its color. Each variant automatically uses its color photo.') }}</p>
                        </div>
                        <button type="button" class="btn btn-outline" @click="regenerateVariants()" :disabled="loadingVariants">
                            <i class="ph ph-arrows-clockwise"></i> <span x-text="loadingVariants ? '{{ __('Regenerating…') }}' : '{{ __('Regenerate combinations') }}'"></span>
                        </button>
                    </div>

                    {{-- Grouped by Color --}}
                    <div class="mt-6 space-y-6">
                        <template x-for="(col, cIdx) in colors" :key="cIdx">
                            <div class="rounded-2xl border-2 border-border bg-neutral-0 overflow-hidden shadow-xs">
                                {{-- Color Group Header --}}
                                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-neutral-50 p-4">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-block h-6 w-6 rounded-full border border-neutral-300 shadow-xs" :style="`background-color: ${col.hex_code || '#2563EB'}`"></span>
                                        <h3 class="font-bold text-base text-title" x-text="col.name || col.hex_code"></h3>
                                        <template x-if="getColorMedia(col)">
                                            <img :src="getColorMedia(col)?.url" :alt="col.name" class="h-8 w-8 rounded-lg object-cover border border-border">
                                        </template>
                                    </div>
                                    <div class="text-sm font-semibold text-body">
                                        {{ __('Subtotal: ') }} <strong class="text-primary" x-text="getColorSubtotal(col.name || col.hex_code)"></strong> {{ __('pcs') }}
                                    </div>
                                </div>

                                {{-- Size Rows for this Color --}}
                                <div class="p-4 space-y-3 divide-y divide-border/60">
                                    <template x-for="(variant, index) in variants" :key="variant.id || index">
                                        <div x-show="String(variant.attributes?.color || '').toLowerCase() === String(col.name || col.hex_code).toLowerCase()" class="pt-3 first:pt-0">
                                            <input type="hidden" :name="`variants[${index}][id]`" :value="variant.id || ''">
                                            <input type="hidden" :name="`variants[${index}][color_id]`" :value="col.id || variant.color_id || ''">
                                            <input type="hidden" :name="`variants[${index}][size]`" :value="variant.size || variant.attributes?.size || ''">
                                            <input type="hidden" :name="`variants[${index}][media_id]`" :value="variant.media_id || col.swatch_media_id || ''">
                                            <input type="hidden" :name="`variants[${index}][attributes_json]`" :value="JSON.stringify(variant.attributes)">

                                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 items-center">
                                                {{-- Size Pill --}}
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-3 py-1.5 font-bold text-white text-xs min-w-[48px]" x-text="variant.attributes?.size || variant.size || 'Size'"></span>
                                                    <span class="text-xs text-body font-mono" x-text="variant.sku"></span>
                                                </div>

                                                {{-- SKU --}}
                                                <div>
                                                    <label class="text-[11px] font-medium text-body">{{ __('SKU') }}</label>
                                                    <input class="form-input text-xs" required :name="`variants[${index}][sku]`" x-model="variant.sku">
                                                </div>

                                                {{-- Stock Count (Count) --}}
                                                <div class="bg-primary/5 rounded-xl p-2 border border-primary/20">
                                                    <label class="text-[11px] font-bold text-primary">{{ __('Stock Count (pcs)') }}</label>
                                                    <input type="number" min="0" class="form-input text-xs font-bold" required :name="`variants[${index}][stock_quantity]`" x-model="variant.stock_quantity" placeholder="0">
                                                </div>

                                                {{-- Price override --}}
                                                <div>
                                                    <label class="text-[11px] font-medium text-body">{{ __('Sample Price ($)') }}</label>
                                                    <input type="number" step="0.01" min="0.01" class="form-input text-xs" required :name="`variants[${index}][price]`" x-model="variant.price">
                                                </div>

                                                {{-- Status --}}
                                                <div>
                                                    <label class="text-[11px] font-medium text-body">{{ __('Availability') }}</label>
                                                    <select class="form-input text-xs" :name="`variants[${index}][status]`" x-model="variant.status">
                                                        <option value="active">{{ __('Active') }}</option>
                                                        <option value="out_of_stock">{{ __('Out of stock') }}</option>
                                                        <option value="archived">{{ __('Archived') }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div x-show="variants.length === 0" class="rounded-2xl border border-dashed border-border p-8 text-center text-body">
                            {{ __('Return to Step 2 (Colors & Sizes) to add colors and sizes.') }}
                        </div>
                    </div>
                </section>
                
                <div class="sticky bottom-4 flex justify-between gap-3 rounded-2xl bg-neutral-0/95 p-3 shadow-lg backdrop-blur">
                    <x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 3]) }}">{{ __('Back') }}</x-ui.button>
                    <x-forms.submit :label="__('Save matrix and review')" />
                </div>
            </form>

        {{-- STEP 5: WhatsApp Readiness & Publish --}}
        @else
            <section class="grid gap-6 lg:grid-cols-[1fr_360px]">
                <div class="space-y-6">
                    {{-- Readiness Checks --}}
                    <div class="section-card">
                        <h2 class="heading-5 text-title">{{ __('WhatsApp Readiness Review') }}</h2>
                        <p class="text-sm text-body">{{ __('Resolve every blocking issue before publishing this product to your live storefront and WhatsApp catalog.') }}</p>
                        
                        <div class="mt-5 space-y-3">
                            @forelse($readinessIssues as $issue)
                                <div class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 p-4">
                                    <i class="ph ph-warning-circle mt-0.5 text-warning"></i>
                                    <div>
                                        <p class="font-semibold text-title">{{ str($issue['code'])->replace('_', ' ')->title() }}</p>
                                        <p class="text-sm text-body">{{ $issue['message'] }}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="flex items-start gap-3 rounded-xl border border-success/30 bg-success/10 p-4">
                                    <i class="ph ph-check-circle mt-0.5 text-success"></i>
                                    <div>
                                        <p class="font-semibold text-title">{{ __('Ready for WhatsApp & Storefront') }}</p>
                                        <p class="text-sm text-body">{{ __('The product has public HTTPS images, valid prices, and configured color & size stock matrix.') }}</p>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Pricing & Color Summary --}}
                    <div class="section-card">
                        <h3 class="heading-6 text-title mb-4">{{ __('Pricing & Matrix Summary') }}</h3>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="rounded-xl border border-border p-4 bg-neutral-50/50">
                                <span class="text-xs text-body font-medium">{{ __('Single Sample Sale Price') }}</span>
                                <p class="text-xl font-bold text-title mt-1">${{ number_format((float) ($product->single_piece_price ?? 9.00), 2) }} <span class="text-xs font-normal text-body">/ piece</span></p>
                            </div>
                            <div class="rounded-xl border border-emerald-500/30 p-4 bg-emerald-50/30">
                                <span class="text-xs text-emerald-800 font-medium">{{ __('Wholesale Bulk Price') }}</span>
                                <p class="text-xl font-bold text-emerald-700 mt-1">${{ number_format((float) ($product->wholesale_price ?? 6.50), 2) }} <span class="text-xs font-normal text-emerald-600">/ piece</span></p>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-border">
                            <h4 class="text-xs font-bold text-body uppercase tracking-wider mb-2">{{ __('Configured Color Swatches') }}</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach($product->colors as $c)
                                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-white px-2.5 py-1 text-xs font-semibold">
                                        <span class="h-3 w-3 rounded-full border border-neutral-300" style="background-color: {{ $c->hex_code ?: '#2563EB' }}"></span>
                                        <span>{{ $c->display_name }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sidebar Action Card --}}
                <aside class="section-card h-fit">
                    <h3 class="font-semibold text-title">{{ __('Publish Status') }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-body">{{ __('Gallery Images') }}</dt>
                            <dd class="font-semibold text-title">{{ $product->gallery->count() }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-body">{{ __('Colors') }}</dt>
                            <dd class="font-semibold text-title">{{ $product->colors->count() }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-body">{{ __('Total Variants') }}</dt>
                            <dd class="font-semibold text-title">{{ $product->variants->count() }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-body">{{ __('Total Stock Count') }}</dt>
                            <dd class="font-bold text-primary">{{ $product->variants->sum('stock_quantity') }} pcs</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-body">{{ __('Current Status') }}</dt>
                            <dd><span class="badge badge-soft">{{ str($product->status)->title() }}</span></dd>
                        </div>
                    </dl>

                    <form method="POST" action="{{ route('user.commerce.products.publish', $product) }}" class="mt-6 space-y-3">
                        @csrf @method('PUT')
                        <input type="hidden" name="status" value="active">
                        <x-forms.submit :label="__('Publish product')" class="w-full" :disabled="$readinessIssues !== []" />
                    </form>

                    @if($product->status !== 'draft')
                        <form method="POST" action="{{ route('user.commerce.products.publish', $product) }}" class="mt-2">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="draft">
                            <x-forms.submit :label="__('Return to draft')" variant="outline" class="w-full" />
                        </form>
                    @endif
                </aside>
            </section>
        @endif
    </div>
</x-layouts.user>
