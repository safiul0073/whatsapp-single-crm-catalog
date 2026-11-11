@php
    try {
        $switcherCurrencies = \App\Modules\Currencies\Models\Currency::active()->orderBy('sort_order')->orderBy('code')->get(['code', 'name', 'symbol']);
    } catch (\Throwable $e) {
        $switcherCurrencies = collect();
    }
    $currentCurrency = currency_display_code();
    $nativeDropdown = ($variant ?? 'topbar') === 'native';
@endphp

@if($switcherCurrencies->count() > 1)
{{-- "native" uses <details> so it works on pages without the dashboard dropdown JS (public site). --}}
<{{ $nativeDropdown ? 'details' : 'div' }} class="{{ $nativeDropdown ? 'group' : 'dropdown-wrapper' }} relative">
    <{{ $nativeDropdown ? 'summary' : 'button type=button' }}
            class="flex cursor-pointer list-none items-center gap-1.5 rounded-sm px-2.5 py-2 text-sm text-neutral-500 transition-colors hover:bg-neutral-50"
            @unless($nativeDropdown) data-action="toggle-dropdown" data-target="currencyDropdown" aria-haspopup="true" @endunless
            aria-label="{{ __('Switch currency') }}">
        <i class="ph ph-currency-circle-dollar text-lg"></i>
        <span>{{ $currentCurrency }}</span>
        <i class="ph ph-caret-down text-xs text-neutral-400"></i>
    </{{ $nativeDropdown ? 'summary' : 'button' }}>

    <div id="currencyDropdown"
         class="{{ $nativeDropdown ? '' : 'dropdown-panel' }} bg-neutral-0 absolute end-0 top-full z-50 mt-2 max-h-80 w-56 overflow-y-auto rounded-md border border-neutral-100 shadow-lg"
         role="menu">
        <div class="py-1">
            @foreach($switcherCurrencies as $currency)
                <form method="POST" action="{{ route('currency.switch') }}">
                    @csrf
                    <input type="hidden" name="code" value="{{ $currency->code }}">
                    <button type="submit"
                            class="flex w-full items-center gap-3 px-4 py-2 text-sm transition-colors hover:bg-neutral-50 {{ $currency->code === $currentCurrency ? 'text-primary font-medium bg-primary/5' : 'text-neutral-600' }}"
                            role="menuitem">
                        <span class="min-w-[2.5rem] text-xs uppercase text-neutral-400">{{ $currency->code }}</span>
                        {{ currency_normalize_symbol($currency->symbol) }} {{ $currency->name }}
                        @if($currency->code === $currentCurrency)
                            <i class="ph ph-check ms-auto text-primary"></i>
                        @endif
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</{{ $nativeDropdown ? 'details' : 'div' }}>
@endif
