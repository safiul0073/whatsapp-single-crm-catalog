<x-layouts.user :title="'Page Builder — ' . $page->title">
    @push('styles')
    <style>
        .ghost-section-drag { opacity: 0.4; }
        .section-drag-over { border-color: var(--primary-color, #0866FF) !important; background-color: rgba(8, 102, 255, 0.04); }
    </style>
    @endpush

    {{-- Hidden Media Picker Bridge for Section Image Uploads/Selection --}}
    <div id="storefrontMediaPickerBridge" class="hidden" data-media-picker data-media-accept="image">
        <button type="button" data-media-picker-trigger></button>
    </div>

    <div
        x-data="storefrontPageEditor({{ Illuminate\Support\Js::from($sections) }}, {
            defaultSliderId: {{ $sliders->first()?->id ?? 1 }}
        })"
        x-init="init()"
        class="space-y-6"
    >
        {{-- Hidden Form That Performs the Real Submission --}}
        <form
            id="storefrontPageForm"
            method="POST"
            action="{{ route('user.commerce.storefront.pages.update', $page->id) }}"
            class="hidden"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="save_action" value="stay">
            <input type="hidden" id="publishNowInput" name="publish_now" value="0">
            <input type="hidden" id="storefrontSectionsInput" name="sections" value="">
            <input type="hidden" name="title" value="{{ $page->title }}" id="formPageTitle">
            <input type="hidden" name="slug" value="{{ $page->slug }}" id="formPageSlug">
            <input type="hidden" name="seo[title]" value="{{ $page->seo['title'] ?? '' }}" id="formSeoTitle">
            <input type="hidden" name="seo[description]" value="{{ $page->seo['description'] ?? '' }}" id="formSeoDescription">
        </form>

        {{-- Top App Bar / Builder Header --}}
        <header class="flex flex-col gap-4 rounded-2xl border border-neutral-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1.5">
                <nav class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                    <a href="{{ route('user.commerce.storefront.index') }}" class="hover:text-primary transition-colors flex items-center gap-1">
                        <i class="ph ph-storefront text-sm"></i>
                        <span>{{ __('Storefront Studio') }}</span>
                    </a>
                    <span>/</span>
                    <a href="{{ route('user.commerce.storefront.index') }}#pages" class="hover:text-primary transition-colors">
                        {{ __('Pages & CMS') }}
                    </a>
                    <span>/</span>
                    <span class="text-neutral-800 font-semibold dark:text-neutral-200">{{ $page->title }}</span>
                </nav>

                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-xl sm:text-2xl font-black text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                        <span>{{ $page->title }}</span>
                        <button
                            type="button"
                            @click="pageModalOpen = true"
                            class="text-neutral-400 hover:text-primary transition-colors"
                            title="{{ __('Edit Page Details & SEO') }}"
                        >
                            <i class="ph ph-pencil-simple text-base"></i>
                        </button>
                    </h1>

                    <code class="rounded-md bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 font-mono dark:bg-neutral-800 dark:text-neutral-400">
                        /{{ $page->slug }}
                    </code>

                    @if($page->status === 'published')
                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center gap-1.5 border border-emerald-200/60 dark:border-emerald-900/40">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            {{ __('Published Live') }}
                        </span>
                    @else
                        <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200/60 dark:border-amber-900/40">
                            {{ __('Draft') }}
                        </span>
                    @endif

                    @if($page->published_at)
                        <span class="text-xs text-neutral-400">
                            {{ __('Last published') }} {{ $page->published_at->diffForHumans() }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Action Controls --}}
            <div class="flex flex-wrap items-center gap-2 pt-2 sm:pt-0 border-t border-neutral-100 dark:border-neutral-800 sm:border-0">
                <a
                    href="{{ route('user.commerce.storefront.index') }}"
                    class="btn btn-ghost btn-sm rounded-xl text-xs font-semibold flex items-center gap-1.5"
                >
                    <i class="ph ph-arrow-left text-sm"></i>
                    <span>{{ __('Back to Studio') }}</span>
                </a>

                <button
                    type="button"
                    @click="pageModalOpen = true"
                    class="btn btn-outline btn-sm rounded-xl text-xs font-semibold flex items-center gap-1.5"
                >
                    <i class="ph ph-sliders-horizontal text-sm"></i>
                    <span>{{ __('Page SEO') }}</span>
                </button>

                <form method="POST" action="{{ route('user.commerce.storefront.pages.preview', $page->id) }}" target="_blank" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm rounded-xl text-xs font-semibold flex items-center gap-1.5">
                        <i class="ph ph-eye text-sm"></i>
                        <span>{{ __('Preview') }}</span>
                    </button>
                </form>

                <button
                    type="button"
                    @click="submitForm(false)"
                    class="btn btn-outline btn-sm rounded-xl text-xs font-semibold flex items-center gap-1.5 border-neutral-300 dark:border-neutral-700"
                >
                    <i class="ph ph-floppy-disk text-sm"></i>
                    <span>{{ __('Save Draft') }}</span>
                </button>

                <button
                    type="button"
                    @click="submitForm(true)"
                    class="btn btn-primary btn-sm rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm"
                >
                    <i class="ph ph-cloud-arrow-up text-sm"></i>
                    <span>{{ __('Publish Live') }}</span>
                </button>
            </div>
        </header>

        {{-- Alert notification if error exists --}}
        <template x-if="error">
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs font-medium text-red-800 flex items-center justify-between dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                <div class="flex items-center gap-2">
                    <i class="ph ph-warning-circle text-base"></i>
                    <span x-text="error"></span>
                </div>
                <button type="button" @click="error = ''" class="text-red-600 hover:text-red-900">
                    <i class="ph ph-x text-sm"></i>
                </button>
            </div>
        </template>

        @if($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs font-medium text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300 space-y-1">
                <strong class="font-bold block">{{ __('Please correct the following errors before saving:') }}</strong>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 2-Column Builder Workspace --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Left Column: Sections Outline (Page Structure) --}}
            <aside class="lg:col-span-5 xl:col-span-4 rounded-2xl border border-neutral-200 bg-white p-4 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-4">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                    <div class="flex items-center gap-2">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-stack text-base"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-neutral-900 dark:text-neutral-100">{{ __('Page Sections') }}</h2>
                            <p class="text-[11px] text-neutral-500 dark:text-neutral-400">{{ __('Drag or reorder layout') }}</p>
                        </div>
                    </div>

                    <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-semibold text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                        <span x-text="sections.length"></span> {{ __('sections') }}
                    </span>
                </div>

                {{-- Add Section Button --}}
                <button
                    type="button"
                    @click="addSectionModalOpen = true"
                    class="w-full rounded-xl border-2 border-dashed border-primary/40 bg-primary/5 py-3 text-xs font-bold text-primary hover:bg-primary/10 transition-colors flex items-center justify-center gap-2"
                >
                    <i class="ph ph-plus-circle text-base"></i>
                    <span>{{ __('Add New Section') }}</span>
                </button>

                {{-- Section Cards List --}}
                <div class="space-y-2 max-h-[680px] overflow-y-auto pr-1">
                    <template x-for="(sec, idx) in sections" :key="sec.key">
                        <div
                            class="group relative rounded-xl border p-3 transition-all cursor-pointer select-none"
                            :class="{
                                'border-primary ring-2 ring-primary/20 bg-primary/5 shadow-xs dark:bg-primary/10': selectedIndex === idx,
                                'border-neutral-200 bg-white hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900': selectedIndex !== idx,
                                'opacity-60': !sec.active
                            }"
                            @click="selectSection(idx)"
                            draggable="true"
                            @dragstart="dragIndex = idx"
                            @dragover.prevent
                            @drop.prevent="move(dragIndex, idx); dragIndex = null"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="cursor-grab text-neutral-400 hover:text-neutral-700 active:cursor-grabbing">
                                        <i class="ph ph-dots-six-vertical text-base"></i>
                                    </span>

                                    <span
                                        class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-sm"
                                        :class="selectedIndex === idx ? 'bg-primary text-white' : 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300'"
                                    >
                                        <i :class="getSectionIcon(sec.type)"></i>
                                    </span>

                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-bold text-neutral-900 dark:text-neutral-100" x-text="sec.config.title || getSectionLabel(sec.type)"></p>
                                        <p class="text-[10px] text-neutral-400 uppercase tracking-wider truncate" x-text="getSectionLabel(sec.type)"></p>
                                    </div>
                                </div>

                                {{-- Quick Mini Actions --}}
                                <div class="flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-opacity">
                                    {{-- Active Status Dot --}}
                                    <button
                                        type="button"
                                        @click.stop="sec.active = !sec.active"
                                        class="p-1 rounded text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200"
                                        :title="sec.active ? 'Visible on storefront' : 'Hidden'"
                                    >
                                        <span class="block h-2 w-2 rounded-full" :class="sec.active ? 'bg-emerald-500' : 'bg-neutral-300 dark:bg-neutral-600'"></span>
                                    </button>

                                    {{-- Move Up --}}
                                    <button
                                        type="button"
                                        @click.stop="move(idx, idx - 1)"
                                        :disabled="idx === 0"
                                        class="p-1 rounded text-neutral-400 hover:text-neutral-700 disabled:opacity-20 dark:hover:text-neutral-200"
                                        title="{{ __('Move Up') }}"
                                    >
                                        <i class="ph ph-arrow-up text-xs"></i>
                                    </button>

                                    {{-- Move Down --}}
                                    <button
                                        type="button"
                                        @click.stop="move(idx, idx + 1)"
                                        :disabled="idx === sections.length - 1"
                                        class="p-1 rounded text-neutral-400 hover:text-neutral-700 disabled:opacity-20 dark:hover:text-neutral-200"
                                        title="{{ __('Move Down') }}"
                                    >
                                        <i class="ph ph-arrow-down text-xs"></i>
                                    </button>

                                    {{-- Duplicate --}}
                                    <button
                                        type="button"
                                        @click.stop="duplicate(idx)"
                                        class="p-1 rounded text-neutral-400 hover:text-primary dark:hover:text-primary"
                                        title="{{ __('Duplicate Section') }}"
                                    >
                                        <i class="ph ph-copy text-xs"></i>
                                    </button>

                                    {{-- Remove --}}
                                    <button
                                        type="button"
                                        @click.stop="remove(idx)"
                                        class="p-1 rounded text-neutral-400 hover:text-red-600 dark:hover:text-red-400"
                                        title="{{ __('Remove Section') }}"
                                    >
                                        <i class="ph ph-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Empty Sections State --}}
                    <template x-if="sections.length === 0">
                        <div class="rounded-xl border border-dashed border-neutral-300 p-8 text-center dark:border-neutral-800">
                            <i class="ph ph-puzzle-piece text-3xl text-neutral-300 dark:text-neutral-700 block mb-2"></i>
                            <p class="text-xs font-semibold text-neutral-600 dark:text-neutral-400">{{ __('No sections added yet.') }}</p>
                            <p class="text-[11px] text-neutral-400 mt-1 mb-3">{{ __('Add hero sliders, product carousels, or promo banners to design your page.') }}</p>
                            <button
                                type="button"
                                @click="addSectionModalOpen = true"
                                class="btn btn-primary btn-sm rounded-lg text-xs"
                            >
                                {{ __('+ Choose First Section') }}
                            </button>
                        </div>
                    </template>
                </div>
            </aside>

            {{-- Right Column: Active Section Form Editor --}}
            <main class="lg:col-span-7 xl:col-span-8 rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-6">
                <template x-if="currentSection">
                    <div class="space-y-6">
                        {{-- Active Section Header Bar --}}
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                            <div class="flex items-center gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary">
                                    <i :class="getSectionIcon(currentSection.type) + ' text-xl'"></i>
                                </span>
                                <div>
                                    <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                                        <span x-text="getSectionLabel(currentSection.type)"></span>
                                    </h3>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400" x-text="getSectionDesc(currentSection.type)"></p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                {{-- Active Switch --}}
                                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                    <input type="checkbox" x-model="currentSection.active" class="sr-only peer">
                                    <span class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('Active') }}</span>
                                    <div class="relative w-9 h-5 bg-neutral-200 peer-focus:outline-hidden rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-neutral-600 peer-checked:bg-primary"></div>
                                </label>

                                {{-- Delete Current Section --}}
                                <button
                                    type="button"
                                    @click="remove(selectedIndex)"
                                    class="btn btn-ghost btn-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 rounded-lg text-xs"
                                    title="{{ __('Delete Section') }}"
                                >
                                    <i class="ph ph-trash text-sm"></i>
                                    <span>{{ __('Remove') }}</span>
                                </button>
                            </div>
                        </div>

                        {{-- Section Settings Tabs / Cards --}}
                        <div class="space-y-5">
                            {{-- Section General Title & Subtitle --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="form-label text-xs font-semibold">{{ __('Section Heading / Title') }}</label>
                                    <input
                                        type="text"
                                        x-model="currentSection.config.title"
                                        placeholder="e.g. Featured Collection"
                                        class="form-input text-xs rounded-xl"
                                    >
                                </div>
                                <div class="space-y-1">
                                    <label class="form-label text-xs font-semibold">{{ __('Subtitle / Subheading') }}</label>
                                    <input
                                        type="text"
                                        x-model="currentSection.config.subtitle"
                                        placeholder="e.g. Explore our bestselling heavyweight garments"
                                        class="form-input text-xs rounded-xl"
                                    >
                                </div>
                            </div>

                            {{-- SPECIFIC CONFIGURATION BY SECTION TYPE --}}

                            {{-- 1. Hero Slider Section --}}
                            <template x-if="currentSection.type === 'hero_slider'">
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200">
                                            {{ __('Hero Slider Configuration') }}
                                        </span>
                                        <a href="{{ route('user.commerce.storefront.index') }}#sliders" class="text-xs text-primary font-semibold hover:underline">
                                            {{ __('Manage Sliders & Slides →') }}
                                        </a>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="form-label text-xs">{{ __('Select Hero Slider') }} *</label>
                                        <select
                                            x-model.number="currentSection.config.slider_id"
                                            class="form-input text-xs rounded-xl"
                                        >
                                            @forelse($sliders as $slider)
                                                <option value="{{ $slider->id }}">{{ $slider->name }} (ID: {{ $slider->id }})</option>
                                            @empty
                                                <option value="1">{{ __('Default Slider #1') }}</option>
                                            @endforelse
                                        </select>
                                        <p class="text-[11px] text-neutral-500">{{ __('The slides, images, and headlines configured under this slider will display full-width.') }}</p>
                                    </div>
                                </div>
                            </template>

                            {{-- 2. Product Carousel / Grid Section --}}
                            <template x-if="currentSection.type === 'product_carousel'">
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-4">
                                    <span class="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200 block">
                                        {{ __('Product Source & Filter') }}
                                    </span>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="space-y-1">
                                            <label class="form-label text-xs">{{ __('Product Feed Source') }} *</label>
                                            <select
                                                x-model="currentSection.config.source"
                                                class="form-input text-xs rounded-xl"
                                            >
                                                <option value="new-arrivals">{{ __('New Arrivals (Latest Products)') }}</option>
                                                <option value="featured">{{ __('Featured Products') }}</option>
                                                <option value="deals">{{ __('On Sale / Deals') }}</option>
                                                <option value="category">{{ __('By Specific Category') }}</option>
                                                <option value="brand">{{ __('By Specific Brand') }}</option>
                                                <option value="manual">{{ __('Manual Product Selection') }}</option>
                                            </select>
                                        </div>

                                        <div class="space-y-1">
                                            <label class="form-label text-xs">{{ __('Product Count Limit') }}</label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="24"
                                                x-model.number="currentSection.config.limit"
                                                class="form-input text-xs rounded-xl"
                                            >
                                        </div>
                                    </div>

                                    {{-- Category Selector (When source === 'category') --}}
                                    <div x-show="currentSection.config.source === 'category'" class="space-y-1">
                                        <label class="form-label text-xs">{{ __('Select Category') }}</label>
                                        <select
                                            x-model="currentSection.config.category"
                                            class="form-input text-xs rounded-xl"
                                        >
                                            <option value="">{{ __('— Choose Category —') }}</option>
                                            @foreach($categories as $cat)
                                                <option value="{{ $cat->slug }}">{{ $cat->name }} (/{{ $cat->slug }})</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Brand Selector (When source === 'brand') --}}
                                    <div x-show="currentSection.config.source === 'brand'" class="space-y-1">
                                        <label class="form-label text-xs">{{ __('Select Brand') }}</label>
                                        <select
                                            x-model="currentSection.config.brand"
                                            class="form-input text-xs rounded-xl"
                                        >
                                            <option value="">{{ __('— Choose Brand —') }}</option>
                                            @foreach($brands as $b)
                                                <option value="{{ $b->slug }}">{{ $b->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </template>

                            {{-- 3. Promo Banner, Content Grid, Testimonials (Visual Item Repeater!) --}}
                            <template x-if="['promo_banner', 'content_grid', 'testimonials', 'benefit_banner'].includes(currentSection.type)">
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200 block">
                                                {{ __('Section Content Items & Cards') }}
                                            </span>
                                            <p class="text-[11px] text-neutral-500">
                                                {{ __('Add cards with images, titles, descriptions, and links.') }}
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            @click="addItem(currentSection)"
                                            class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1"
                                        >
                                            <i class="ph ph-plus text-xs"></i>
                                            <span>{{ __('Add Item') }}</span>
                                        </button>
                                    </div>

                                    <div class="space-y-3">
                                        <template x-for="(item, itemIdx) in (currentSection.config.items || [])" :key="itemIdx">
                                            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs dark:border-neutral-700 dark:bg-neutral-900 space-y-3">
                                                <div class="flex items-center justify-between border-b border-neutral-100 pb-2 dark:border-neutral-800">
                                                    <span class="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                                                        #<span x-text="itemIdx + 1"></span> &mdash; <span x-text="item.title || 'Untitled Item'"></span>
                                                    </span>
                                                    <button
                                                        type="button"
                                                        @click="removeItem(currentSection, itemIdx)"
                                                        class="text-xs text-red-600 hover:text-red-800 font-semibold flex items-center gap-1"
                                                    >
                                                        <i class="ph ph-trash"></i>
                                                        <span>{{ __('Delete') }}</span>
                                                    </button>
                                                </div>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <div class="space-y-1">
                                                        <label class="form-label text-[11px]">{{ __('Item Title') }}</label>
                                                        <input
                                                            type="text"
                                                            x-model="item.title"
                                                            placeholder="e.g. 100% Combed Cotton"
                                                            class="form-input text-xs rounded-lg"
                                                        >
                                                    </div>

                                                    <div class="space-y-1" x-show="currentSection.type !== 'testimonials'">
                                                        <label class="form-label text-[11px]">{{ __('Link URL') }}</label>
                                                        <input
                                                            type="text"
                                                            x-model="item.link"
                                                            placeholder="e.g. /products or https://..."
                                                            class="form-input text-xs rounded-lg font-mono"
                                                        >
                                                    </div>
                                                </div>

                                                <div class="space-y-1">
                                                    <label class="form-label text-[11px]">{{ __('Item Description / Text') }}</label>
                                                    <textarea
                                                        rows="2"
                                                        x-model="item.text"
                                                        placeholder="Write card text or customer review..."
                                                        class="form-input text-xs rounded-lg"
                                                    ></textarea>
                                                </div>

                                                {{-- Image Picker with Gallery Integration --}}
                                                <div x-show="['promo_banner', 'content_grid', 'testimonials'].includes(currentSection.type)" class="space-y-1.5 pt-1">
                                                    <label class="form-label text-[11px] font-semibold">{{ __('Card Image') }}</label>
                                                    <div class="flex items-center gap-3">
                                                        {{-- Thumbnail Preview --}}
                                                        <template x-if="item.image">
                                                            <div class="relative h-14 w-14 shrink-0 rounded-lg border border-neutral-200 overflow-hidden bg-neutral-100 dark:border-neutral-700">
                                                                <img :src="item.image" alt="Preview" class="h-full w-full object-cover">
                                                                <button
                                                                    type="button"
                                                                    @click="removeImage({ sectionIndex: selectedIndex, itemIndex: itemIdx, field: 'image' })"
                                                                    class="absolute top-0.5 right-0.5 grid h-4 w-4 place-items-center rounded-full bg-black/70 text-white text-[10px] hover:bg-red-600"
                                                                    title="Remove image"
                                                                >
                                                                    &times;
                                                                </button>
                                                            </div>
                                                        </template>

                                                        <div class="flex-1 space-y-1.5">
                                                            <div class="flex items-center gap-2">
                                                                <button
                                                                    type="button"
                                                                    @click="openMediaPicker({ sectionIndex: selectedIndex, itemIndex: itemIdx, field: 'image' })"
                                                                    class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1.5"
                                                                >
                                                                    <i class="ph ph-images text-sm text-primary"></i>
                                                                    <span>{{ __('Choose from Image Gallery') }}</span>
                                                                </button>
                                                                <span class="text-[11px] text-neutral-400">{{ __('or paste URL below') }}</span>
                                                            </div>
                                                            <input
                                                                type="text"
                                                                x-model="item.image"
                                                                placeholder="https://... or /storage/..."
                                                                class="form-input text-xs rounded-lg font-mono"
                                                            >
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="!currentSection.config.items || currentSection.config.items.length === 0">
                                            <p class="text-xs text-neutral-400 italic py-2">
                                                {{ __('No items added yet. Click "+ Add Item" above to add your first card.') }}
                                            </p>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- 4. Countdown Deal Section --}}
                            <template x-if="currentSection.type === 'countdown_deal'">
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-4">
                                    <span class="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200 block">
                                        {{ __('Flash Deal Timer & Banner') }}
                                    </span>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="space-y-1">
                                            <label class="form-label text-xs">{{ __('Deal Ends At (Date & Time)') }} *</label>
                                            <input
                                                type="datetime-local"
                                                x-model="currentSection.config.ends_at"
                                                class="form-input text-xs rounded-xl"
                                            >
                                        </div>

                                        <div class="space-y-1">
                                            <label class="form-label text-xs">{{ __('Banner Background Image') }}</label>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    @click="openMediaPicker({ sectionIndex: selectedIndex, field: 'image' })"
                                                    class="btn btn-outline btn-sm rounded-lg text-xs font-semibold flex items-center gap-1.5 shrink-0"
                                                >
                                                    <i class="ph ph-images text-sm text-primary"></i>
                                                    <span>{{ __('Choose Image') }}</span>
                                                </button>
                                                <input
                                                    type="text"
                                                    x-model="currentSection.config.image"
                                                    placeholder="/storage/... or https://..."
                                                    class="form-input text-xs rounded-lg font-mono flex-1"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            {{-- 5. Rich Text Block --}}
                            <template x-if="currentSection.type === 'rich_text'">
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 dark:border-neutral-800 dark:bg-neutral-800/40 space-y-2">
                                    <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200 block">
                                        {{ __('Rich Text Content / HTML') }} *
                                    </label>
                                    <textarea
                                        rows="8"
                                        x-model="currentSection.config.text"
                                        placeholder="Write formatted article, story, or store policies..."
                                        class="form-input text-xs rounded-xl font-mono"
                                    ></textarea>
                                </div>
                            </template>

                            {{-- Common Action Button (CTA Button on Section) --}}
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50/50 p-4 dark:border-neutral-800 dark:bg-neutral-800/30 space-y-3">
                                <span class="text-xs font-semibold text-neutral-700 dark:text-neutral-300 block">
                                    {{ __('Optional Section Action Button') }}
                                </span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <label class="form-label text-[11px]">{{ __('Button Label') }}</label>
                                        <input
                                            type="text"
                                            x-model="currentSection.config.button_text"
                                            placeholder="e.g. Shop Now"
                                            class="form-input text-xs rounded-xl"
                                        >
                                    </div>
                                    <div class="space-y-1">
                                        <label class="form-label text-[11px]">{{ __('Button Link') }}</label>
                                        <input
                                            type="text"
                                            x-model="currentSection.config.button_link"
                                            placeholder="e.g. /products or /categories/hoodies"
                                            class="form-input text-xs rounded-xl font-mono"
                                        >
                                    </div>
                                </div>
                            </div>

                            {{-- Visibility & Scheduling Accordion / Controls --}}
                            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800 space-y-3">
                                <span class="text-xs font-semibold text-neutral-700 dark:text-neutral-300 block">
                                    {{ __('Screen Visibility & Schedule (Optional)') }}
                                </span>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                                    <div class="space-y-1">
                                        <label class="form-label text-[11px]">{{ __('Target Devices') }}</label>
                                        <select x-model="currentSection.visibility" class="form-input text-xs rounded-xl">
                                            <option value="all">{{ __('All Screens (Desktop & Mobile)') }}</option>
                                            <option value="desktop">{{ __('Desktop Only') }}</option>
                                            <option value="mobile">{{ __('Mobile Only') }}</option>
                                        </select>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="form-label text-[11px]">{{ __('Display Start Date') }}</label>
                                        <input
                                            type="datetime-local"
                                            x-model="currentSection.starts_at"
                                            class="form-input text-xs rounded-xl"
                                        >
                                    </div>

                                    <div class="space-y-1">
                                        <label class="form-label text-[11px]">{{ __('Display End Date') }}</label>
                                        <input
                                            type="datetime-local"
                                            x-model="currentSection.ends_at"
                                            class="form-input text-xs rounded-xl"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Bottom Floating Save Trigger for convenience --}}
                        <div class="flex items-center justify-between border-t border-neutral-100 pt-4 dark:border-neutral-800">
                            <span class="text-xs text-neutral-400">
                                {{ __('Editing section') }} <strong x-text="selectedIndex + 1"></strong> of <strong x-text="sections.length"></strong>
                            </span>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="submitForm(false)"
                                    class="btn btn-outline btn-sm rounded-xl text-xs font-semibold"
                                >
                                    {{ __('Save Draft') }}
                                </button>
                                <button
                                    type="button"
                                    @click="submitForm(true)"
                                    class="btn btn-primary btn-sm rounded-xl text-xs font-bold"
                                >
                                    {{ __('Publish Live') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="!currentSection">
                    <div class="py-16 text-center space-y-3">
                        <span class="grid h-12 w-12 mx-auto place-items-center rounded-2xl bg-neutral-100 text-neutral-400 dark:bg-neutral-800">
                            <i class="ph ph-hand-pointing text-2xl"></i>
                        </span>
                        <h3 class="text-sm font-bold text-neutral-800 dark:text-neutral-200">{{ __('No Section Selected') }}</h3>
                        <p class="text-xs text-neutral-400 max-w-sm mx-auto">
                            {{ __('Choose an existing section from the outline on the left, or add a new section to customize your page layout.') }}
                        </p>
                        <button
                            type="button"
                            @click="addSectionModalOpen = true"
                            class="btn btn-primary btn-sm rounded-xl text-xs font-semibold"
                        >
                            {{ __('+ Add New Section') }}
                        </button>
                    </div>
                </template>
            </main>
        </div>

        {{-- MODAL 1: ADD SECTION CATALOG MODAL --}}
        <div
            x-show="addSectionModalOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @click.self="addSectionModalOpen = false"
            @keydown.escape.window="addSectionModalOpen = false"
        >
            <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 max-h-[85vh] flex flex-col space-y-4">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                    <div>
                        <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">{{ __('Add New Section to Page') }}</h3>
                        <p class="text-xs text-neutral-500">{{ __('Select a section type to insert into your storefront layout.') }}</p>
                    </div>
                    <button type="button" @click="addSectionModalOpen = false" class="text-neutral-400 hover:text-neutral-700">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                </div>

                {{-- Catalog Grid of Sections --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 overflow-y-auto pr-1 flex-1 py-1">
                    @php
                        $catalogSections = [
                            ['type' => 'hero_slider', 'name' => 'Hero Banner Slider', 'icon' => 'ph-presentation', 'badge' => 'Featured', 'desc' => 'High-impact full width slider with CTAs.'],
                            ['type' => 'product_carousel', 'name' => 'Product Carousel', 'icon' => 'ph-t-shirt', 'badge' => 'Essential', 'desc' => 'Dynamic collection of new arrivals, deals, or wholesale styles.'],
                            ['type' => 'promo_banner', 'name' => 'Promo Banners', 'icon' => 'ph-tag', 'badge' => 'Marketing', 'desc' => 'Promotional cards with photos, discount tags, and links.'],
                            ['type' => 'category_grid', 'name' => 'Category Grid', 'icon' => 'ph-squares-four', 'badge' => 'Navigation', 'desc' => 'Visual category tiles to guide shoppers.'],
                            ['type' => 'category_carousel', 'name' => 'Category Carousel', 'icon' => 'ph-browsers', 'badge' => 'Navigation', 'desc' => 'Horizontal sliding category carousel.'],
                            ['type' => 'content_grid', 'name' => 'Content Feature Grid', 'icon' => 'ph-layout', 'badge' => 'Editorial', 'desc' => '2-3 column cards highlighting craftsmanship and fabrics.'],
                            ['type' => 'countdown_deal', 'name' => 'Flash Countdown Deal', 'icon' => 'ph-timer', 'badge' => 'Urgency', 'desc' => 'Live countdown clock driving limited wholesale orders.'],
                            ['type' => 'benefit_banner', 'name' => 'Trust & Benefits Strip', 'icon' => 'ph-shield-check', 'badge' => 'Trust', 'desc' => 'Guarantees, express freight, and store ratio badges.'],
                            ['type' => 'testimonials', 'name' => 'Customer Testimonials', 'icon' => 'ph-chat-circle-dots', 'badge' => 'Social Proof', 'desc' => 'Customer reviews and feedback quotes.'],
                            ['type' => 'rich_text', 'name' => 'Rich Text Block', 'icon' => 'ph-article', 'badge' => 'Content', 'desc' => 'Brand story, about us, or store policy block.'],
                            ['type' => 'newsletter', 'name' => 'Newsletter Signup', 'icon' => 'ph-envelope-simple', 'badge' => 'Leads', 'desc' => 'Capture wholesale and retail shopper emails.'],
                            ['type' => 'cta', 'name' => 'Call to Action Banner', 'icon' => 'ph-megaphone-simple', 'badge' => 'Conversion', 'desc' => 'Bold banner driving shoppers to view full catalog.'],
                            ['type' => 'brand_strip', 'name' => 'Brand Logos Strip', 'icon' => 'ph-trademark', 'badge' => 'Partners', 'desc' => 'Showcase authorized manufacturing brands.'],
                        ];
                    @endphp

                    @foreach($catalogSections as $item)
                        <button
                            type="button"
                            @click="add('{{ $item['type'] }}')"
                            class="flex items-start gap-3 rounded-xl border border-neutral-200 bg-white p-3 text-left hover:border-primary hover:bg-primary/5 hover:shadow-xs transition-all dark:border-neutral-800 dark:bg-neutral-800/40 dark:hover:border-primary"
                        >
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                <i class="ph {{ $item['icon'] }} text-lg"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-neutral-900 dark:text-neutral-100">{{ $item['name'] }}</span>
                                    <span class="rounded bg-neutral-100 px-1.5 py-0.2 text-[10px] font-semibold text-neutral-500 dark:bg-neutral-700 dark:text-neutral-300">
                                        {{ $item['badge'] }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-2">{{ $item['desc'] }}</p>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- MODAL 2: PAGE SETTINGS & SEO MODAL --}}
        <div
            x-show="pageModalOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @click.self="pageModalOpen = false"
            @keydown.escape.window="pageModalOpen = false"
            x-data="{
                title: '{{ addslashes($page->title) }}',
                slug: '{{ addslashes($page->slug) }}',
                seoTitle: '{{ addslashes($page->seo['title'] ?? '') }}',
                seoDesc: '{{ addslashes($page->seo['description'] ?? '') }}',
                saveMeta() {
                    document.getElementById('formPageTitle').value = this.title;
                    document.getElementById('formPageSlug').value = this.slug;
                    document.getElementById('formSeoTitle').value = this.seoTitle;
                    document.getElementById('formSeoDescription').value = this.seoDesc;
                    this.pageModalOpen = false;
                }
            }"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-4">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                    <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">{{ __('Page Settings & SEO') }}</h3>
                    <button type="button" @click="pageModalOpen = false" class="text-neutral-400 hover:text-neutral-700">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="space-y-1">
                        <label class="form-label text-xs font-semibold">{{ __('Page Title') }} *</label>
                        <input
                            type="text"
                            x-model="title"
                            required
                            class="form-input text-xs rounded-xl"
                        >
                    </div>

                    <div class="space-y-1">
                        <label class="form-label text-xs font-semibold">{{ __('URL Slug') }} *</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400 font-mono text-xs">/</span>
                            <input
                                type="text"
                                x-model="slug"
                                required
                                class="form-input pl-6 text-xs font-mono rounded-xl"
                            >
                        </div>
                        <p class="text-[11px] text-neutral-400">{{ __('The home page slug must remain "home".') }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="form-label text-xs font-semibold">{{ __('SEO Meta Title') }}</label>
                        <input
                            type="text"
                            x-model="seoTitle"
                            placeholder="e.g. Fashion Fiest | Heavyweight Streetwear Essentials"
                            class="form-input text-xs rounded-xl"
                        >
                    </div>

                    <div class="space-y-1">
                        <label class="form-label text-xs font-semibold">{{ __('SEO Meta Description') }}</label>
                        <textarea
                            rows="3"
                            x-model="seoDesc"
                            placeholder="Brief description for search engines..."
                            class="form-input text-xs rounded-xl"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-neutral-100 pt-3 dark:border-neutral-800">
                    <button type="button" @click="pageModalOpen = false" class="btn btn-ghost btn-sm rounded-xl text-xs">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" @click="saveMeta()" class="btn btn-primary btn-sm rounded-xl text-xs font-semibold">
                        {{ __('Apply Changes') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('js/storefront-editor.js') }}"></script>
    @endpush
</x-layouts.user>
