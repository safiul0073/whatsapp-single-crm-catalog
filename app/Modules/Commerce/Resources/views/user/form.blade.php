@php
    $isEdit = isset($product);
    $galleryState = $product?->gallery?->map(fn ($item) => ['id' => $item->media_id, 'name' => $item->media?->name, 'url' => $item->media?->url, 'type' => $item->media_type, 'alt_text' => $item->alt_text, 'is_primary' => $item->is_primary])->values()->all() ?? [];
    $optionState = $product?->options?->map(fn ($option) => ['name' => $option->name, 'code' => $option->code, 'values_csv' => $option->values->pluck('value')->implode(', ')])->values()->all() ?? [['name' => 'Size', 'code' => 'size', 'values_csv' => 'S, M, L, XL'], ['name' => 'Color', 'code' => 'color', 'values_csv' => 'Black, White']];
    $steps = [
        1 => ['label' => __('Details'), 'icon' => 'ph-note-pencil'],
        2 => ['label' => __('Gallery'), 'icon' => 'ph-images'],
        3 => ['label' => __('Options'), 'icon' => 'ph-sliders-horizontal'],
        4 => ['label' => __('Variants'), 'icon' => 'ph-stack'],
        5 => ['label' => __('Review'), 'icon' => 'ph-check-circle'],
    ];
@endphp

<x-layouts.user :title="$isEdit ? __('Edit product') : __('Create product')">
    <div
        class="mx-auto max-w-7xl space-y-6"
        x-data="commerceProductWizard(@js(['gallery' => $galleryState, 'options' => $optionState, 'variants' => $variantPreview, 'previewUrl' => $isEdit ? route('user.commerce.products.variants.preview', $product) : null]))"
        @change="dirty = true"
        @media-picker:selected="addMedia($event.detail.media)"
    >
        <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary">{{ __('WhatsApp store product') }}</p>
                <h1 class="heading-3 text-title">{{ $isEdit ? $product->name : __('Create a product') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Your progress is saved after every step. Publish only after the WhatsApp readiness review passes.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($isEdit && $product->status === 'active')
                    <x-ui.button variant="outline" href="{{ route('commerce.products.public', $product->slug) }}" target="_blank"><i class="ph ph-arrow-square-out"></i> {{ __('Preview') }}</x-ui.button>
                @endif
                <x-ui.button variant="outline" href="{{ route('user.commerce.products.index') }}">{{ __('Save and exit') }}</x-ui.button>
            </div>
        </header>

        @include('commerce::user.partials.help', ['helpKey' => 'product_form'])

        <nav class="overflow-x-auto rounded-2xl bg-neutral-0 p-4" aria-label="{{ __('Product setup progress') }}">
            <ol class="grid min-w-[620px] grid-cols-5 text-sm">
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

        @if($step === 1)
            <form method="POST" action="{{ $isEdit ? route('user.commerce.products.details.update', $product) : route('user.commerce.products.store') }}" class="space-y-6" @submit="markSaved()">
                @csrf @if($isEdit) @method('PUT') @endif
                <section class="section-card">
                    <div><h2 class="heading-5 text-title">{{ __('Product details') }}</h2><p class="text-sm text-body">{{ __('Start with customer-facing information. You can return and edit it later.') }}</p></div>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div><label class="form-label" for="name">{{ __('Product name') }}</label><input id="name" class="form-input" name="name" required maxlength="255" value="{{ old('name', $product?->name) }}" placeholder="{{ __('e.g. Lightweight performance jacket') }}"></div>
                        <div><label class="form-label" for="category_id">{{ __('Category') }}</label><select id="category_id" class="form-input" name="category_id"><option value="">{{ __('Select category') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product?->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                        <div>
                            <div class="flex items-center justify-between gap-3"><label class="form-label" for="brand_id">{{ __('Brand') }}</label><a class="text-xs font-semibold text-primary hover:underline" href="{{ route('user.commerce.brands.index') }}">{{ __('Manage brands') }}</a></div>
                            <select id="brand_id" class="form-input" name="brand_id">
                                <option value="">{{ __('Select brand') }}</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected(old('brand_id', $product?->brand_id) == $brand->id)>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between gap-3"><label class="form-label" for="audience_id">{{ __('Audience') }}</label><a class="text-xs font-semibold text-primary hover:underline" href="{{ route('user.commerce.audiences.index') }}">{{ __('Manage audiences') }}</a></div>
                            <select id="audience_id" class="form-input" name="audience_id">
                                <option value="">{{ __('Select audience') }}</option>
                                @foreach($audiences as $audience)
                                    <option value="{{ $audience->id }}" @selected(old('audience_id', $product?->audience_id) == $audience->id)>{{ $audience->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label class="form-label" for="condition">{{ __('Condition') }}</label><select id="condition" class="form-input" name="condition"><option value="new" @selected(old('condition', $product?->condition ?? 'new') === 'new')>{{ __('New') }}</option><option value="refurbished" @selected(old('condition', $product?->condition) === 'refurbished')>{{ __('Refurbished') }}</option><option value="used" @selected(old('condition', $product?->condition) === 'used')>{{ __('Used') }}</option></select></div>
                        <div><label class="form-label" for="country_of_origin">{{ __('Country of origin') }}</label><input id="country_of_origin" class="form-input" name="country_of_origin" maxlength="2" value="{{ old('country_of_origin', $product?->country_of_origin ?? 'BD') }}"></div>
                        <div class="md:col-span-2"><label class="form-label" for="description">{{ __('Description') }}</label><textarea id="description" class="form-input min-h-32" name="description" maxlength="5000" placeholder="{{ __('Describe the material, fit, feel, and ideal use.') }}">{{ old('description', $product?->description) }}</textarea></div>
                        <div class="md:col-span-2"><label class="form-label" for="care_information">{{ __('Care information') }}</label><textarea id="care_information" class="form-input min-h-24" name="care_information" maxlength="2000">{{ old('care_information', $product?->care_information) }}</textarea></div>
                    </div>
                </section>
                <div class="sticky bottom-4 flex justify-end rounded-2xl bg-neutral-0/95 p-3 shadow-lg backdrop-blur"><x-forms.submit :label="$isEdit ? __('Save and continue') : __('Create draft and continue')" /></div>
            </form>
        @elseif($step === 2)
            <form method="POST" action="{{ route('user.commerce.products.gallery.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                <section class="section-card">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="heading-5 text-title">{{ __('Product gallery') }}</h2><p class="text-sm text-body">{{ __('Add up to 10 images and one MP4 video. Choose one image as the Meta catalog cover.') }}</p></div><x-media.picker name="gallery_picker" accept="all" :multiple="true" :label="__('Add from Media Library')" :hint="__('Upload several files or select existing media.')" /></div>
                    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <template x-for="(item, index) in gallery" :key="item.id">
                            <article class="overflow-hidden rounded-2xl border border-border bg-neutral-0">
                                <div class="aspect-[4/3] bg-section"><template x-if="item.type === 'image'"><img :src="item.url" :alt="item.alt_text" class="h-full w-full object-cover"></template><template x-if="item.type === 'video'"><video :src="item.url" controls preload="metadata" class="h-full w-full object-cover"></video></template></div>
                                <div class="space-y-3 p-3">
                                    <input type="hidden" :name="`media[${index}][id]`" :value="item.id"><input type="hidden" :name="`media[${index}][is_primary]`" :value="item.is_primary ? 1 : 0">
                                    <div class="flex items-center justify-between gap-2"><span class="badge badge-soft" x-text="item.type"></span><span x-show="item.is_primary" class="badge bg-primary/10 text-primary">{{ __('Primary') }}</span></div>
                                    <input class="form-input" :name="`media[${index}][alt_text]`" x-model="item.alt_text" maxlength="255" placeholder="{{ __('Image description') }}">
                                    <div class="flex flex-wrap gap-2"><button type="button" class="btn btn-sm btn-outline" x-show="item.type === 'image' && !item.is_primary" @click="setPrimary(index)">{{ __('Make primary') }}</button><button type="button" class="row-action" @click="moveMedia(index, -1)" :disabled="index === 0" aria-label="{{ __('Move earlier') }}"><i class="ph ph-arrow-left"></i></button><button type="button" class="row-action" @click="moveMedia(index, 1)" :disabled="index === gallery.length - 1" aria-label="{{ __('Move later') }}"><i class="ph ph-arrow-right"></i></button><button type="button" class="row-action text-error" @click="removeMedia(index)" aria-label="{{ __('Remove media') }}"><i class="ph ph-trash"></i></button></div>
                                </div>
                            </article>
                        </template>
                        <div x-show="gallery.length === 0" class="col-span-full rounded-2xl border border-dashed border-border p-10 text-center text-body"><i class="ph ph-images text-4xl text-neutral-300"></i><p class="mt-2 font-semibold text-title">{{ __('Add your product photos') }}</p><p class="text-sm">{{ __('Clear front, back, detail, and fit photos improve buyer confidence.') }}</p></div>
                    </div>
                </section>
                <div class="sticky bottom-4 flex justify-between gap-3 rounded-2xl bg-neutral-0/95 p-3 shadow-lg backdrop-blur"><x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 1]) }}">{{ __('Back') }}</x-ui.button><x-forms.submit :label="__('Save gallery and continue')" /></div>
            </form>
        @elseif($step === 3)
            <form method="POST" action="{{ route('user.commerce.products.options.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                <section class="section-card"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="heading-5 text-title">{{ __('Product options') }}</h2><p class="text-sm text-body">{{ __('Options generate sellable combinations. Use up to five, such as size, color, material, fit, or pattern.') }}</p></div><button type="button" class="btn btn-outline" @click="addOption()" :disabled="options.length >= 5"><i class="ph ph-plus"></i> {{ __('Add option') }}</button></div><div class="mt-5 space-y-3"><template x-for="(option, index) in options" :key="index"><div class="grid gap-3 rounded-2xl border border-border p-4 md:grid-cols-[1fr_1fr_2fr_auto]"><input class="form-input" required :name="`options[${index}][name]`" x-model="option.name" placeholder="{{ __('Name, e.g. Size') }}"><input class="form-input" required :name="`options[${index}][code]`" x-model="option.code" placeholder="{{ __('Code, e.g. size') }}"><input class="form-input" required :name="`options[${index}][values_csv]`" x-model="option.values_csv" placeholder="{{ __('Comma-separated values: S, M, L') }}"><button type="button" class="row-action text-error" @click="options.splice(index, 1)" aria-label="{{ __('Remove option') }}"><i class="ph ph-trash"></i></button></div></template></div></section>
                <div class="sticky bottom-4 flex justify-between gap-3 rounded-2xl bg-neutral-0/95 p-3 shadow-lg backdrop-blur"><x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 2]) }}">{{ __('Back') }}</x-ui.button><x-forms.submit :label="__('Generate variants')" /></div>
            </form>
        @elseif($step === 4)
            <form method="POST" action="{{ route('user.commerce.products.variants.update', $product) }}" class="space-y-6" @submit="markSaved()">
                @csrf @method('PUT')
                <section class="section-card"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="heading-5 text-title">{{ __('Variants and inventory') }}</h2><p class="text-sm text-body">{{ __('Review every sellable combination. Existing inventory and order-linked variants are preserved safely.') }}</p></div><button type="button" class="btn btn-outline" @click="regenerateVariants()" :disabled="loadingVariants"><i class="ph ph-arrows-clockwise"></i> <span x-text="loadingVariants ? '{{ __('Generating…') }}' : '{{ __('Regenerate') }}'"></span></button></div>
                    <div class="mt-5 space-y-4"><template x-for="(variant, index) in variants" :key="variant.id || JSON.stringify(variant.attributes)"><article class="rounded-2xl border border-border p-4"><input type="hidden" :name="`variants[${index}][id]`" :value="variant.id || ''"><input type="hidden" :name="`variants[${index}][attributes_json]`" :value="JSON.stringify(variant.attributes)"><div class="mb-3 flex flex-wrap gap-2"><template x-for="(value, key) in variant.attributes" :key="key"><span class="badge badge-soft"><span x-text="key"></span>: <strong x-text="value"></strong></span></template></div><div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><div><label class="form-label">{{ __('SKU') }}</label><input class="form-input" required :name="`variants[${index}][sku]`" x-model="variant.sku"></div><div><label class="form-label">{{ __('Meta retailer ID') }}</label><input class="form-input" required :name="`variants[${index}][meta_retailer_id]`" x-model="variant.meta_retailer_id"></div><div><label class="form-label">{{ __('USD price') }}</label><input type="number" step="0.01" min="0.01" class="form-input" required :name="`variants[${index}][price]`" x-model="variant.price"></div><div><label class="form-label">{{ __('Compare-at price') }}</label><input type="number" step="0.01" min="0" class="form-input" :name="`variants[${index}][compare_at_price]`" x-model="variant.compare_at_price"></div><div><label class="form-label">{{ __('Stock') }}</label><input type="number" min="0" class="form-input" required :name="`variants[${index}][stock_quantity]`" x-model="variant.stock_quantity"></div><div><label class="form-label">{{ __('Weight (kg)') }}</label><input type="number" step="0.001" min="0" class="form-input" :name="`variants[${index}][weight_kg]`" x-model="variant.weight_kg"></div><div><label class="form-label">{{ __('Variant image') }}</label><select class="form-input" :name="`variants[${index}][media_id]`" x-model="variant.media_id"><option value="">{{ __('Use primary image') }}</option>@foreach($product->gallery->where('media_type', 'image') as $galleryItem)<option value="{{ $galleryItem->media_id }}">{{ $galleryItem->media?->name }}</option>@endforeach</select></div><div><label class="form-label">{{ __('Availability') }}</label><select class="form-input" :name="`variants[${index}][status]`" x-model="variant.status"><option value="active">{{ __('Active') }}</option><option value="out_of_stock">{{ __('Out of stock') }}</option><option value="archived">{{ __('Archived') }}</option></select></div></div></article></template><div x-show="variants.length === 0" class="rounded-2xl border border-dashed border-border p-8 text-center text-body">{{ __('Return to Options and add at least one option value.') }}</div></div>
                </section>
                <div class="sticky bottom-4 flex justify-between gap-3 rounded-2xl bg-neutral-0/95 p-3 shadow-lg backdrop-blur"><x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 3]) }}">{{ __('Back') }}</x-ui.button><x-forms.submit :label="__('Save inventory and review')" /></div>
            </form>
        @else
            <section class="grid gap-6 lg:grid-cols-[1fr_360px]">
                <div class="section-card"><h2 class="heading-5 text-title">{{ __('WhatsApp readiness review') }}</h2><p class="text-sm text-body">{{ __('Resolve every blocking issue before publishing this product to your catalog.') }}</p><div class="mt-5 space-y-3">@forelse($readinessIssues as $issue)<div class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 p-4"><i class="ph ph-warning-circle mt-0.5 text-warning"></i><div><p class="font-semibold text-title">{{ str($issue['code'])->replace('_', ' ')->title() }}</p><p class="text-sm text-body">{{ $issue['message'] }}</p></div></div>@empty<div class="flex items-start gap-3 rounded-xl border border-success/30 bg-success/10 p-4"><i class="ph ph-check-circle mt-0.5 text-success"></i><div><p class="font-semibold text-title">{{ __('Ready for WhatsApp') }}</p><p class="text-sm text-body">{{ __('The product has a public HTTPS image and valid sellable variants.') }}</p></div></div>@endforelse</div></div>
                <aside class="section-card h-fit"><h3 class="font-semibold text-title">{{ __('Publish status') }}</h3><dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between gap-3"><dt class="text-body">{{ __('Gallery') }}</dt><dd class="font-semibold text-title">{{ $product->gallery->count() }}</dd></div><div class="flex justify-between gap-3"><dt class="text-body">{{ __('Variants') }}</dt><dd class="font-semibold text-title">{{ $product->variants->count() }}</dd></div><div class="flex justify-between gap-3"><dt class="text-body">{{ __('Current status') }}</dt><dd><span class="badge badge-soft">{{ str($product->status)->title() }}</span></dd></div></dl><form method="POST" action="{{ route('user.commerce.products.publish', $product) }}" class="mt-5 space-y-3">@csrf @method('PUT')<input type="hidden" name="status" value="active"><x-forms.submit :label="__('Publish product')" class="w-full" :disabled="$readinessIssues !== []" /></form>@if($product->status !== 'draft')<form method="POST" action="{{ route('user.commerce.products.publish', $product) }}" class="mt-2">@csrf @method('PUT')<input type="hidden" name="status" value="draft"><x-forms.submit :label="__('Return to draft')" variant="outline" class="w-full" /></form>@endif</aside>
            </section>
            <div class="sticky bottom-4 flex justify-start rounded-2xl bg-neutral-0/95 p-3 shadow-lg backdrop-blur"><x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', ['product' => $product, 'step' => 4]) }}">{{ __('Back to variants') }}</x-ui.button></div>
        @endif
    </div>
</x-layouts.user>
