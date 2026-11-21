@php
    $pickerId = $id ?? 'live_products';
    $pickerTitle = $label ?? __('Featured Products');
    $pickerHelp = $help ?? __('Shoppers can add these products to their cart directly while watching.');
    $oldSelected = collect((array) old('product_ids', []))->map(fn ($v): int => (int) $v)->values()->all();
@endphp

@if(isset($products) && $products->count())
    <div
        x-data="{
            query: '',
            selected: {{ Illuminate\Support\Js::from($oldSelected) }},
            toggle(id) {
                const idx = this.selected.indexOf(id);
                if (idx === -1) {
                    this.selected.push(id);
                } else {
                    this.selected.splice(idx, 1);
                }
            },
            matches(name) {
                const q = this.query.trim().toLowerCase();
                return q === '' || name.toLowerCase().includes(q);
            }
        }"
        class="space-y-1.5"
    >
        <div class="flex items-center justify-between gap-2">
            <label for="{{ $pickerId }}_search" class="form-label mb-0 text-xs font-semibold">
                {{ $pickerTitle }}
                <span class="font-normal text-neutral-400">({{ __('Optional') }})</span>
            </label>
            <div class="flex items-center gap-2 text-[11px]">
                <span
                    x-show="selected.length > 0"
                    x-cloak
                    class="rounded-full bg-primary/10 px-2 py-0.5 font-semibold text-primary"
                    x-text="selected.length + ' {{ __('selected') }}'"
                ></span>
                <button
                    type="button"
                    x-show="selected.length > 0"
                    x-cloak
                    @click="selected = []"
                    class="font-medium text-neutral-500 hover:text-rose-600 transition-colors cursor-pointer"
                >
                    {{ __('Clear') }}
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
            <div class="relative border-b border-neutral-100 dark:border-neutral-800">
                <i class="ph ph-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-neutral-400"></i>
                <input
                    id="{{ $pickerId }}_search"
                    type="text"
                    x-model="query"
                    placeholder="{{ __('Search catalog products…') }}"
                    class="w-full bg-transparent py-2 pl-9 pr-3 text-xs text-title placeholder:text-neutral-400 focus:outline-none"
                >
            </div>

            <div class="max-h-44 divide-y divide-neutral-100 overflow-y-auto dark:divide-neutral-800">
                @foreach($products as $prod)
                    <label
                        x-show="matches({{ Illuminate\Support\Js::from($prod->name) }})"
                        class="flex cursor-pointer items-center gap-2.5 px-3 py-2 text-xs transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-800/60"
                        :class="selected.includes({{ (int) $prod->id }}) ? 'bg-primary/5 dark:bg-primary/10' : ''"
                    >
                        <input
                            type="checkbox"
                            name="product_ids[]"
                            value="{{ $prod->id }}"
                            :checked="selected.includes({{ (int) $prod->id }})"
                            @change="toggle({{ (int) $prod->id }})"
                            class="form-checkbox rounded text-primary shrink-0"
                        >
                        <span class="truncate font-medium text-title">{{ $prod->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <p class="text-[11px] text-body">{{ $pickerHelp }}</p>
    </div>
@endif
