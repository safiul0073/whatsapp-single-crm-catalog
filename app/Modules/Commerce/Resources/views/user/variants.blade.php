<x-layouts.user :title="__('Variant options')">
    <div
        class="space-y-6"
        x-data="{
            csrfToken: '{{ csrf_token() }}',
            savingId: null,
            successMsg: '',
            errorMsg: '',
            async updateOption(presetId, data) {
                this.savingId = presetId;
                this.successMsg = '';
                this.errorMsg = '';
                try {
                    const response = await fetch(`/user/commerce/variants/${presetId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(data)
                    });
                    const res = await response.json();
                    if (response.ok) {
                        this.successMsg = res.message || '{{ __('Saved successfully') }}';
                        setTimeout(() => { this.successMsg = ''; }, 3000);
                    } else {
                        this.errorMsg = res.message || '{{ __('Could not update option') }}';
                    }
                } catch (e) {
                    this.errorMsg = '{{ __('Network error') }}';
                } finally {
                    this.savingId = null;
                }
            }
        }"
    >
        {{-- Breadcrumb & Title --}}
        <div>
            <nav class="flex items-center gap-1.5 text-xs font-medium text-body mb-1" aria-label="Breadcrumb">
                <a href="{{ route('user.commerce.products.index') }}" class="hover:text-primary transition-colors">{{ __('Products') }}</a>
                <span class="text-neutral-400">/</span>
                <span class="text-neutral-700 font-semibold">{{ __('Variant options') }}</span>
            </nav>
            <h1 class="heading-3 text-title">{{ __('Variant options') }}</h1>
            <p class="mt-1 text-sm text-body">{{ __('Create reusable options like Size M or Color Blue, then select them on product create/edit pages.') }}</p>
        </div>

        @include('commerce::user.partials.help', ['helpKey' => 'categories'])

        {{-- Toast / Feedback Alerts --}}
        <template x-if="successMsg">
            <div class="rounded-xl border border-success/30 bg-success/10 p-3.5 text-sm font-medium text-success flex items-center justify-between transition-all" role="alert">
                <div class="flex items-center gap-2">
                    <i class="ph ph-check-circle text-lg"></i>
                    <span x-text="successMsg"></span>
                </div>
                <button type="button" @click="successMsg = ''" class="text-success hover:opacity-75"><i class="ph ph-x"></i></button>
            </div>
        </template>

        <template x-if="errorMsg">
            <div class="rounded-xl border border-error/30 bg-error/10 p-3.5 text-sm font-medium text-error flex items-center justify-between transition-all" role="alert">
                <div class="flex items-center gap-2">
                    <i class="ph ph-warning-circle text-lg"></i>
                    <span x-text="errorMsg"></span>
                </div>
                <button type="button" @click="errorMsg = ''" class="text-error hover:opacity-75"><i class="ph ph-x"></i></button>
            </div>
        </template>

        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error" role="alert">
                <p class="font-semibold">{{ __('Please fix the following errors:') }}</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 2-Column Grid Layout --}}
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            
            {{-- Left Column: Reusable Variant Options Table --}}
            <section class="section-card p-0 overflow-hidden">
                <div class="flex items-center justify-between border-b border-border bg-white px-5 py-4">
                    <div class="flex items-center gap-3">
                        <h2 class="heading-5 text-title">{{ __('Reusable variant options') }}</h2>
                        <span class="rounded-full bg-neutral-100 border border-neutral-200 px-2.5 py-0.5 text-xs font-bold text-neutral-700">
                            {{ $presets->count() }}
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-neutral-50/80 text-[11px] font-bold uppercase tracking-wider text-body border-b border-border">
                            <tr>
                                <th class="px-5 py-3.5">{{ __('OPTION') }}</th>
                                <th class="px-4 py-3.5 w-36">{{ __('SKU SUFFIX') }}</th>
                                <th class="px-4 py-3.5 w-32">{{ __('PRICE DELTA') }}</th>
                                <th class="px-4 py-3.5 w-40">{{ __('WEIGHT') }}</th>
                                <th class="px-4 py-3.5 w-28 text-center">{{ __('USED BY') }}</th>
                                <th class="px-4 py-3.5 w-24 text-center">{{ __('STATUS') }}</th>
                                <th class="px-5 py-3.5 w-24 text-right">{{ __('ACTION') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/70 bg-white">
                            @forelse ($presets as $preset)
                                <tr
                                    class="group hover:bg-neutral-50/50 transition-colors"
                                    x-data="{
                                        id: {{ $preset->id }},
                                        name: @js($preset->name),
                                        skuSuffix: @js($preset->sku_suffix ?: $preset->name),
                                        priceDelta: @js((string) number_format((float) ($preset->price_delta ?? 0), 2, '.', '')),
                                        weight: @js($preset->weight),
                                        weightUnit: @js($preset->weight_unit ?? 'kg'),
                                        isActive: @js((bool) $preset->is_active),
                                        dirty: false,
                                        markDirty() { this.dirty = true; },
                                        save() {
                                            $data.updateOption(this.id, {
                                                name: this.name,
                                                sku_suffix: this.skuSuffix,
                                                price_delta: parseFloat(this.priceDelta) || 0,
                                                weight: this.weight ? parseFloat(this.weight) : null,
                                                weight_unit: this.weightUnit,
                                                is_active: this.isActive ? 1 : 0
                                            });
                                            this.dirty = false;
                                        },
                                        toggleStatus() {
                                            this.isActive = !this.isActive;
                                            this.save();
                                        }
                                    }"
                                >
                                    {{-- Option Name + Inline Save Button --}}
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="text"
                                                class="form-input text-xs font-semibold h-9 flex-1"
                                                x-model="name"
                                                @input="markDirty()"
                                                @keydown.enter.prevent="save()"
                                            >
                                            <button
                                                type="button"
                                                class="btn btn-xs btn-outline shrink-0 font-medium text-xs px-2.5 h-9"
                                                :class="dirty ? 'border-primary text-primary bg-primary/5' : 'text-body'"
                                                @click="save()"
                                                :disabled="savingId === id"
                                            >
                                                <span x-text="savingId === id ? '...' : '{{ __('Save') }}'"></span>
                                            </button>
                                        </div>
                                    </td>

                                    {{-- SKU Suffix --}}
                                    <td class="px-4 py-3">
                                        <input
                                            type="text"
                                            class="form-input text-xs font-mono uppercase h-9"
                                            x-model="skuSuffix"
                                            @input="markDirty()"
                                            @keydown.enter.prevent="save()"
                                        >
                                    </td>

                                    {{-- Price Delta --}}
                                    <td class="px-4 py-3">
                                        <input
                                            type="number"
                                            step="0.01"
                                            class="form-input text-xs font-medium h-9"
                                            x-model="priceDelta"
                                            @input="markDirty()"
                                            @keydown.enter.prevent="save()"
                                            placeholder="0.00"
                                        >
                                    </td>

                                    {{-- Weight --}}
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1">
                                            <input
                                                type="number"
                                                step="0.001"
                                                class="form-input text-xs font-medium h-9 w-16"
                                                x-model="weight"
                                                @input="markDirty()"
                                                @keydown.enter.prevent="save()"
                                                placeholder="0.0"
                                            >
                                            <select
                                                class="form-input text-xs h-9 w-16 px-1.5"
                                                x-model="weightUnit"
                                                @change="markDirty(); save()"
                                            >
                                                <option value="kg">kg</option>
                                                <option value="g">g</option>
                                                <option value="lb">lb</option>
                                                <option value="oz">oz</option>
                                            </select>
                                        </div>
                                    </td>

                                    {{-- Used By --}}
                                    <td class="px-4 py-3 text-center">
                                        @php
                                            $count = $preset->used_by_products_count;
                                        @endphp
                                        <span class="inline-block text-xs text-body font-medium whitespace-nowrap">
                                            {{ trans_choice(':count product|:count products', $count, ['count' => $count]) }}
                                        </span>
                                    </td>

                                    {{-- Status Toggle Switch --}}
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                            :class="isActive ? 'bg-primary' : 'bg-neutral-200'"
                                            @click="toggleStatus()"
                                            :aria-pressed="isActive"
                                            aria-label="{{ __('Toggle status for :name', ['name' => $preset->name]) }}"
                                        >
                                            <span
                                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                                                :class="isActive ? 'translate-x-5' : 'translate-x-0'"
                                            ></span>
                                        </button>
                                    </td>

                                    {{-- Delete Action --}}
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST" action="{{ route('user.commerce.variants.destroy', $preset) }}" class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-1 rounded-lg bg-red-50 hover:bg-red-100 text-error px-2.5 py-1.5 text-xs font-semibold transition shadow-2xs"
                                                data-confirm
                                                data-confirm-title="{{ __('Delete :name?', ['name' => $preset->name]) }}"
                                                data-confirm-body="{{ __('This variant option will be deleted.') }}"
                                                data-confirm-label="{{ __('Delete') }}"
                                                data-confirm-variant="error"
                                                aria-label="{{ __('Delete :name', ['name' => $preset->name]) }}"
                                            >
                                                <i class="ph ph-trash"></i> {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center text-body">
                                        <i class="ph ph-squares-four text-4xl text-neutral-300"></i>
                                        <p class="mt-2 font-semibold text-title">{{ __('No variant options created yet') }}</p>
                                        <p class="text-xs text-neutral-500 mt-0.5">{{ __('Create your first reusable variant option using the form on the right.') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Right Column: Add Variant Option Form --}}
            <aside class="section-card h-fit sticky top-6">
                <h2 class="heading-5 text-title">{{ __('Add variant option') }}</h2>
                <p class="text-xs text-body mt-1">{{ __('Reusable options can be selected by many products. Stock stays separate per product variant.') }}</p>

                <form method="POST" action="{{ route('user.commerce.variants.store') }}" class="mt-5 space-y-4">
                    @csrf
                    
                    {{-- Name --}}
                    <div>
                        <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="option_name">{{ __('Name') }}</label>
                        <input
                            id="option_name"
                            class="form-input text-sm"
                            name="name"
                            required
                            maxlength="120"
                            value="{{ old('name') }}"
                            placeholder="{{ __('Size M') }}"
                        >
                    </div>

                    {{-- SKU Suffix --}}
                    <div>
                        <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="option_sku_suffix">{{ __('SKU suffix') }}</label>
                        <input
                            id="option_sku_suffix"
                            class="form-input text-sm font-mono uppercase"
                            name="sku_suffix"
                            maxlength="40"
                            value="{{ old('sku_suffix') }}"
                            placeholder="{{ __('M') }}"
                        >
                    </div>

                    {{-- Price Delta --}}
                    <div>
                        <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="option_price_delta">{{ __('Price delta') }}</label>
                        <input
                            id="option_price_delta"
                            type="number"
                            step="0.01"
                            class="form-input text-sm"
                            name="price_delta"
                            value="{{ old('price_delta', '0') }}"
                            placeholder="0"
                        >
                        <span class="text-[11px] text-body mt-1 block">{{ __('Optional adjustment from the product base price.') }}</span>
                    </div>

                    {{-- Weight --}}
                    <div>
                        <label class="form-label text-xs font-bold uppercase tracking-wider text-neutral-700" for="option_weight">{{ __('Weight') }}</label>
                        <div class="flex items-center gap-2 mt-1">
                            <input
                                id="option_weight"
                                type="number"
                                step="0.001"
                                class="form-input text-sm flex-1"
                                name="weight"
                                value="{{ old('weight') }}"
                                placeholder="0.5"
                            >
                            <select
                                name="weight_unit"
                                class="form-input text-sm w-24 px-2"
                            >
                                <option value="kg" {{ old('weight_unit', 'kg') === 'kg' ? 'selected' : '' }}>kg</option>
                                <option value="g" {{ old('weight_unit') === 'g' ? 'selected' : '' }}>g</option>
                                <option value="lb" {{ old('weight_unit') === 'lb' ? 'selected' : '' }}>lb</option>
                                <option value="oz" {{ old('weight_unit') === 'oz' ? 'selected' : '' }}>oz</option>
                            </select>
                        </div>
                    </div>

                    {{-- Available for selection toggle --}}
                    <div class="flex items-center justify-between gap-3 pt-2 pb-1 border-t border-border">
                        <label for="is_active_toggle" class="text-xs font-bold uppercase tracking-wider text-neutral-700 cursor-pointer">
                            {{ __('Available for selection') }}
                        </label>
                        <input type="hidden" name="is_active" value="0">
                        <input
                            type="checkbox"
                            id="is_active_toggle"
                            name="is_active"
                            value="1"
                            class="app-checkbox"
                            checked
                        >
                    </div>

                    <x-forms.submit :label="__('+ Save variant option')" class="w-full justify-center" />
                </form>
            </aside>
        </div>
    </div>
</x-layouts.user>
