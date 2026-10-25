{{-- Searchable customer picker: shows the 10 latest customers, then server-side search by name or phone. --}}
<div
    x-data="{
        open: false, term: '', results: [], selected: null, loading: false, timer: null,
        search() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.loading = true;
                fetch('{{ route('user.commerce.referrals.customers') }}?q=' + encodeURIComponent(this.term), { headers: { Accept: 'application/json' } })
                    .then((r) => r.json()).then((d) => { this.results = d; }).finally(() => { this.loading = false; });
            }, 250);
        },
        pick(c) { this.selected = c; this.open = false; this.term = ''; },
    }"
    x-init="search()"
    @click.outside="open = false"
    class="relative"
>
    <input type="hidden" name="customer_id" :value="selected ? selected.id : ''" required>
    <button type="button" @click="open = !open; $nextTick(() => open && $refs.q.focus())" class="{{ $input }} flex items-center justify-between text-left">
        <span x-text="selected ? selected.name + ' (' + (selected.phone || '') + ')' : '{{ __('Select customer') }}'" :class="selected ? '' : 'text-slate-400'" class="truncate"></span>
        <i class="ph ph-caret-down text-sm text-slate-400"></i>
    </button>
    <div x-show="open" x-transition x-cloak class="absolute z-30 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
        <div class="relative border-b border-slate-100 p-2 dark:border-slate-800">
            <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input x-ref="q" x-model="term" @input="search()" @keydown.enter.prevent="results[0] && pick(results[0])" type="search" placeholder="{{ __('Search name or phone…') }}" class="w-full rounded-lg border border-slate-200 py-2 pl-8 pr-3 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800">
        </div>
        <ul class="max-h-64 overflow-y-auto py-1 text-sm">
            <template x-for="c in results" :key="c.id">
                <li><button type="button" @click="pick(c)" class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-emerald-50 dark:hover:bg-slate-800" :class="selected && selected.id === c.id && 'bg-emerald-50 dark:bg-slate-800'">
                    <span x-text="c.name || '—'" class="font-medium text-slate-800 dark:text-slate-200"></span>
                    <span x-text="c.phone" class="text-xs text-slate-500"></span>
                </button></li>
            </template>
            <li x-show="loading" class="px-3 py-2 text-xs text-slate-400">{{ __('Searching…') }}</li>
            <li x-show="! loading && results.length === 0" class="px-3 py-2 text-xs text-slate-400">{{ __('No customers found.') }}</li>
        </ul>
        <p x-show="! term && results.length" class="border-t border-slate-100 px-3 py-1.5 text-[11px] text-slate-400 dark:border-slate-800">{{ __('Showing latest 10 — type to search.') }}</p>
    </div>
</div>
