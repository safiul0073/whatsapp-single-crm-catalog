@php
    $d = $section->data ?? [];
    $plans = $d['plans'] ?? [];
    $billingCycles = $d['billing_cycles'] ?? [];
    $showBillingTabs = !empty($d['show_billing_cycle_tabs']) && count($billingCycles) > 1;
    $showYearlyToggle = !empty($d['show_yearly_toggle']) && ! $showBillingTabs;
    $planCount = count($plans);
    $saveLabels = [];
    foreach ($billingCycles as $cycle) {
        if (!empty($cycle['key']) && !empty($cycle['save_text'])) {
            $saveLabels[$cycle['key']] = $cycle['save_text'];
        }
    }
@endphp
<section id="pricing" class="spy-section">
    <div class="container">
        <div class="mx-auto max-w-2xl text-center">
            @if (!empty($d['eyebrow']))
                <span class="eyebrow">{{ $d['eyebrow'] }}</span>
            @endif
            @if (!empty($d['heading']))
                <h2 class="heading-1 mt-4">{{ $d['heading'] }}</h2>
            @endif
            @if (!empty($d['subheading']))
                <p class="lead-text mt-4">{{ $d['subheading'] }}</p>
            @endif

            @if ($showBillingTabs)
                <div class="mt-8 inline-flex flex-wrap items-center justify-center gap-3">
                    <div class="inline-flex rounded-full border border-neutral-200 bg-section p-1">
                        @foreach ($billingCycles as $cycle)
                            <button type="button" data-billing-btn="{{ $cycle['key'] }}" class="billing-btn">{{ $cycle['label'] }}</button>
                        @endforeach
                    </div>
                    <span
                        data-billing-save
                        @foreach ($saveLabels as $cycleKey => $saveText)
                            data-save-{{ $cycleKey }}="{{ $saveText }}"
                        @endforeach
                        class="billing-save badge badge-soft hidden"
                    >{{ $saveLabels['yearly'] ?? __('Save 20%') }}</span>
                </div>
            @elseif ($showYearlyToggle)
                <div class="mt-8 inline-flex flex-wrap items-center justify-center gap-3">
                    <div class="inline-flex rounded-full border border-neutral-200 bg-section p-1">
                        <button type="button" data-billing-btn="monthly" class="billing-btn">{{ __('Monthly') }}</button>
                        <button type="button" data-billing-btn="yearly" class="billing-btn">{{ __('Yearly') }}</button>
                    </div>
                    @if (!empty($d['yearly_save_text']))
                        <span data-billing-save class="billing-save badge badge-soft">{{ $d['yearly_save_text'] }}</span>
                    @endif
                </div>
            @endif
        </div>

        @if (!empty($plans))
            <div class="mx-auto mt-12 grid max-w-5xl items-start gap-6 md:grid-cols-{{ min($planCount, 3) }}">
                @foreach ($plans as $plan)
                    @php $isHighlighted = !empty($plan['highlighted']); @endphp
                    <div class="card relative {{ $isHighlighted ? 'border-primary/50 bg-section ring-1 ring-primary/30 md:-translate-y-3 hover:shadow-[0_28px_60px_-30px_rgba(31,170,83,0.5)]' : 'transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_24px_50px_-30px_rgba(10,27,20,0.4)]' }}">
                        @if (!empty($plan['badge']))
                            <span class="absolute -top-4 left-1/2 z-10 inline-flex -translate-x-1/2 items-center rounded-full border border-primary/20 bg-primary px-4 py-1.5 text-xs font-bold tracking-[0.12em] whitespace-nowrap text-neutral-0 uppercase shadow-[0_12px_26px_-12px_rgba(31,170,83,0.8)]">{{ $plan['badge'] }}</span>
                        @endif
                        <p class="font-title text-sm font-bold tracking-wide {{ $isHighlighted ? 'text-primary' : 'text-neutral-500' }} uppercase">{{ $plan['name'] ?? '' }}</p>
                        <p class="mt-3">
                            <span class="font-title text-4xl font-extrabold text-title">
                                $<span
                                    data-monthly="{{ $plan['monthly_price'] ?? '0' }}"
                                    data-yearly="{{ $plan['yearly_price'] ?? $plan['monthly_price'] ?? '0' }}"
                                    @if (array_key_exists('lifetime_price', $plan))
                                        data-lifetime="{{ $plan['lifetime_price'] ?? $plan['yearly_price'] ?? $plan['monthly_price'] ?? '0' }}"
                                    @endif
                                >{{ $plan['monthly_price'] ?? '0' }}</span>
                            </span>
                            <span class="text-body" data-period="/mo">/mo</span>
                        </p>
                        @if (!empty($plan['description']))
                            <p class="m-text mt-2">{{ $plan['description'] }}</p>
                        @endif
                        @if (!empty($plan['features']))
                            <ul class="mt-5 space-y-3 text-sm">
                                @foreach ($plan['features'] as $feature)
                                    <li class="flex items-center gap-2.5 text-title"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary/10 text-primary"><svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span> {{ $feature }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (!empty($plan['cta_text']))
                            <a href="{{ $plan['cta_url'] ?? route('login') }}" class="btn {{ $isHighlighted ? 'btn-primary' : 'btn-outline' }} mt-6 w-full">{{ $plan['cta_text'] }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if (!empty($d['footer_text']))
            <p class="mt-6 text-center text-sm text-body">
                {{ $d['footer_text'] }}
                @if (!empty($d['footer_link_text']) && !empty($d['footer_link_url']))
                    <a href="{{ $d['footer_link_url'] }}" class="font-semibold text-primary hover:underline">{{ $d['footer_link_text'] }} →</a>
                @endif
            </p>
        @endif
    </div>
</section>
