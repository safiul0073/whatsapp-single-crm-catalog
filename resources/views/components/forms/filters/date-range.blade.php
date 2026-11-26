@props(['fromName' => 'from', 'toName' => 'to', 'from' => null, 'to' => null, 'submit' => false, 'placeholder' => 'Any date'])

@php
    $from = (string) ($from ?? request($fromName, ''));
    $to = (string) ($to ?? request($toName, ''));
    $presets = [
        'today' => __('Today'),
        'yesterday' => __('Yesterday'),
        'last7' => __('Last 7 days'),
        'last30' => __('Last 30 days'),
        'this_month' => __('This month'),
        'last_month' => __('Last month'),
    ];
@endphp

<div
    {{ $attributes->merge(['class' => 'relative']) }}
    x-data="{
        from: {{ Js::from($from) }},
        to: {{ Js::from($to) }},
        draftFrom: '',
        draftTo: '',
        hover: '',
        open: false,
        view: null,
        submit: {{ Js::from((bool) $submit) }},
        weekdays: ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],
        init() {
            this.view = this.monthStart(this.from ? this.parse(this.from) : new Date());
        },
        pad(n) { return String(n).padStart(2, '0'); },
        fmt(d) { return d.getFullYear() + '-' + this.pad(d.getMonth() + 1) + '-' + this.pad(d.getDate()); },
        parse(s) { const p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); },
        monthStart(d) { return new Date(d.getFullYear(), d.getMonth(), 1); },
        todayStr() { return this.fmt(new Date()); },
        nice(s) { return s ? this.parse(s).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : ''; },
        get label() {
            if (!this.from && !this.to) { return ''; }
            if (this.from && this.to && this.from === this.to) { return this.nice(this.from); }
            return (this.nice(this.from) || '…') + ' – ' + (this.nice(this.to) || '…');
        },
        get monthLabel() { return this.view.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }); },
        get days() {
            const first = this.view;
            const offset = (first.getDay() + 6) % 7;
            const cells = [];
            for (let i = 0; i < 42; i++) {
                const d = new Date(first.getFullYear(), first.getMonth(), 1 - offset + i);
                cells.push({ key: this.fmt(d), day: d.getDate(), inMonth: d.getMonth() === first.getMonth() });
            }
            return cells;
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.draftFrom = this.from;
                this.draftTo = this.to;
                this.view = this.monthStart(this.from ? this.parse(this.from) : new Date());
            }
        },
        shift(n) { this.view = new Date(this.view.getFullYear(), this.view.getMonth() + n, 1); },
        pick(key) {
            if (!this.draftFrom || this.draftTo) {
                this.draftFrom = key;
                this.draftTo = '';
                return;
            }
            if (key < this.draftFrom) {
                this.draftTo = this.draftFrom;
                this.draftFrom = key;
            } else {
                this.draftTo = key;
            }
            this.apply();
        },
        rangeEnd() { return this.draftTo || (this.draftFrom && this.hover > this.draftFrom ? this.hover : ''); },
        isEdge(key) { return key === this.draftFrom || key === this.rangeEnd(); },
        inRange(key) { const end = this.rangeEnd(); return this.draftFrom && end && key > this.draftFrom && key < end; },
        rangeFor(name) {
            const t = new Date();
            const today = new Date(t.getFullYear(), t.getMonth(), t.getDate());
            const ago = (n) => new Date(today.getFullYear(), today.getMonth(), today.getDate() - n);
            const ranges = {
                today: [today, today],
                yesterday: [ago(1), ago(1)],
                last7: [ago(6), today],
                last30: [ago(29), today],
                this_month: [new Date(today.getFullYear(), today.getMonth(), 1), today],
                last_month: [new Date(today.getFullYear(), today.getMonth() - 1, 1), new Date(today.getFullYear(), today.getMonth(), 0)],
            };
            return ranges[name].map((d) => this.fmt(d));
        },
        preset(name) {
            [this.draftFrom, this.draftTo] = this.rangeFor(name);
            this.apply();
        },
        isPreset(name) {
            const [f, t] = this.rangeFor(name);
            return this.draftFrom === f && this.draftTo === t;
        },
        apply() {
            this.from = this.draftFrom;
            this.to = this.draftTo || this.draftFrom;
            this.open = false;
            this.changed();
        },
        clear() {
            this.from = '';
            this.to = '';
            this.draftFrom = '';
            this.draftTo = '';
            this.open = false;
            this.changed();
        },
        changed() {
            this.$dispatch('date-range-change', { from: this.from, to: this.to });
            if (this.submit) {
                this.$nextTick(() => this.$el.closest('form')?.requestSubmit());
            }
        },
    }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <input type="hidden" name="{{ $fromName }}" :value="from" :disabled="!from">
    <input type="hidden" name="{{ $toName }}" :value="to" :disabled="!to">

    <button
        type="button"
        @click="toggle()"
        :aria-expanded="open"
        aria-haspopup="dialog"
        class="flex h-9 w-full sm:min-w-[12.5rem] items-center gap-2 rounded-lg border bg-neutral-0 pl-3 pr-2 text-left text-xs transition focus:outline-none focus:ring-2 focus:ring-primary/20"
        :class="open ? 'border-primary ring-2 ring-primary/20' : (from ? 'border-primary/50 text-title' : 'border-neutral-200 text-neutral-400 hover:border-neutral-300')"
    >
        <i class="ph ph-calendar-dots shrink-0 text-base" :class="from ? 'text-primary' : 'text-neutral-400'"></i>
        <span class="flex-1 truncate" :class="from ? 'font-medium text-title' : ''" x-text="label || {{ Js::from(__($placeholder)) }}">{{ __($placeholder) }}</span>
        <span x-show="from" x-cloak @click.stop="clear()" role="button" tabindex="0" @keydown.enter.stop.prevent="clear()" class="flex h-5 w-5 items-center justify-center rounded-full text-neutral-400 hover:bg-neutral-100 hover:text-error" title="{{ __('Clear dates') }}">
            <i class="ph ph-x text-[11px]"></i>
        </span>
        <i x-show="!from" class="ph ph-caret-down text-[11px] text-neutral-400"></i>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        role="dialog"
        aria-label="{{ __('Choose date range') }}"
        class="absolute left-0 right-0 z-40 mt-1.5 flex w-full sm:left-auto sm:w-[25rem] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-xl border border-border-soft bg-neutral-0 shadow-xl sm:flex-row"
    >
        <div class="flex gap-0.5 overflow-x-auto border-b border-border-soft p-1.5 sm:w-28 sm:flex-col sm:border-b-0 sm:border-r">
            @foreach ($presets as $key => $presetLabel)
                <button type="button" @click="preset('{{ $key }}')" class="whitespace-nowrap rounded-md px-2 py-1 text-left text-xs transition" :class="isPreset('{{ $key }}') ? 'bg-primary/10 font-semibold text-primary' : 'text-neutral-600 hover:bg-neutral-100 hover:text-title'">{{ $presetLabel }}</button>
            @endforeach
        </div>

        <div class="flex-1 p-2">
            <div class="mb-2 flex items-center justify-between">
                <button type="button" @click="shift(-1)" class="flex h-7 w-7 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-title" aria-label="{{ __('Previous month') }}"><i class="ph ph-caret-left"></i></button>
                <span class="text-xs font-semibold text-title" x-text="monthLabel"></span>
                <button type="button" @click="shift(1)" class="flex h-7 w-7 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-title" aria-label="{{ __('Next month') }}"><i class="ph ph-caret-right"></i></button>
            </div>

            <div class="grid grid-cols-7 text-center text-[10px] font-semibold uppercase text-neutral-400">
                <template x-for="w in weekdays" :key="w"><span class="py-1" x-text="w"></span></template>
            </div>

            <div class="grid grid-cols-7 gap-y-0.5 text-center text-xs" @mouseleave="hover = ''">
                <template x-for="cell in days" :key="cell.key">
                    <div class="relative" :class="inRange(cell.key) ? 'bg-primary/10' : ''">
                        <button
                            type="button"
                            @click="pick(cell.key)"
                            @mouseenter="hover = cell.key"
                            class="relative mx-auto flex h-7 w-7 items-center justify-center rounded-md transition"
                            :class="isEdge(cell.key)
                                ? 'bg-primary font-semibold text-white shadow-xs'
                                : (cell.inMonth ? 'text-title hover:bg-neutral-100' : 'text-neutral-300 hover:bg-neutral-100')"
                            x-text="cell.day"
                        ></button>
                        <span x-show="cell.key === todayStr() && !isEdge(cell.key)" class="pointer-events-none absolute bottom-0.5 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-primary"></span>
                    </div>
                </template>
            </div>

            <div class="mt-1.5 flex items-center justify-between border-t border-border-soft pt-1.5 text-[11px]">
                <span class="text-neutral-500" x-text="draftFrom && !draftTo ? {{ Js::from(__('Pick an end date')) }} : {{ Js::from(__('Pick a start date')) }}"></span>
                <button type="button" @click="clear()" class="font-semibold text-neutral-500 hover:text-error">{{ __('Any time') }}</button>
            </div>
        </div>
    </div>
</div>
