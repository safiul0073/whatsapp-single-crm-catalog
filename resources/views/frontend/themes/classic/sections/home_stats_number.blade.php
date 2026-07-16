@php
    $d = $section->data ?? [];

    $bgImageUrl  = media_url($d['background_image_media_id'] ?? null);

    $card1Label  = $d['card1_label']  ?? __('Annual success rate');
    $card1Target = $d['card1_target'] ?? '98%';
    $card1Badge  = $d['card1_badge']  ?? __('2x · YoY');

    $card2Label  = $d['card2_label']  ?? __('Projects completed');
    $card2Target = $d['card2_target'] ?? '23K';
    $card2Badge  = $d['card2_badge']  ?? __('1x+ · lifetime');
@endphp
<section class="stats-section relative overflow-hidden isolate" aria-labelledby="stats-heading">
    <div class="stats-bg absolute inset-0 z-0 pointer-events-none overflow-hidden" aria-hidden="true">
        <div class="absolute will-change-transform" style="inset: -12% -2%" data-stats-parallax>
            <img class="absolute inset-0 w-full h-full object-cover object-center saturate-[0.92] brightness-[0.96] hue-rotate-[-6deg]"
                src="{{ $bgImageUrl ?? 'assets/images/sections/num_p.webp' }}" alt="" loading="lazy"
                onerror="this.style.display = 'none'" />
            <span class="stats-bg-tint absolute inset-0"></span>
            <span class="stats-bg-grid absolute inset-0"></span>
        </div>
        <span
            class="stats-bg-fade stats-bg-fade--top absolute left-0 right-0 top-0 h-24 z-1 pointer-events-none"></span>
        <span
            class="stats-bg-fade stats-bg-fade--bot absolute left-0 right-0 bottom-0 h-24 z-1 pointer-events-none"></span>
    </div>

    <div class="section-container relative z-2 flex flex-col gap-[clamp(36px,4vw,56px)]">
        <div class="grid grid-cols-12 gap-[clamp(18px,2vw,28px)] items-stretch">
            <div class="stats-grid-cards col-span-6 grid grid-cols-2 gap-[clamp(18px,2vw,28px)]">
                <div class="stats-card relative p-[clamp(28px,3.4vw,44px)] rounded-3xl border border-[rgba(15,15,73,0.1)] bg-accent-lime overflow-hidden min-h-[280px] flex flex-col">
                    <div class="flex items-center gap-2 mb-[22px]" aria-hidden="true">
                        <span class="h-px flex-1 bg-[rgba(15,15,73,0.18)]"></span><span
                            class="h-px flex-1 bg-[rgba(15,15,73,0.18)]"></span><span
                            class="h-px flex-1 bg-[rgba(15,15,73,0.18)]"></span>
                    </div>
                    <div class="font-display text-[clamp(16px,1.6vw,20px)] font-bold tracking-body text-brand-navy-ink">
                        {{ $card1Label }}</div>
                    <div class="mt-auto pt-[clamp(48px,6vw,72px)] flex items-end gap-[clamp(16px,2vw,28px)]">
                        <span
                            class="inline-flex items-center gap-2 flex-none font-display text-[clamp(13px,1.2vw,16px)] font-bold tracking-[-0.01em] text-brand-navy-ink pb-[10px]">{{ $card1Badge }}</span>
                        <span
                            class="ml-auto font-display text-[clamp(48px,6.4vw,84px)] font-extrabold tracking-display leading-[0.9] text-brand-navy-ink tabular-nums inline-flex items-baseline whitespace-nowrap">{{ $card1Target }}</span>
                    </div>
                </div>
                <div class="stats-card stats-card--green relative p-[clamp(28px,3.4vw,44px)] rounded-3xl border border-[rgba(15,15,73,0.1)] bg-brand-green text-white overflow-hidden min-h-[280px] flex flex-col">
                    <div class="flex items-center gap-2 mb-[22px]" aria-hidden="true"><span
                            class="h-px flex-1"></span><span class="h-px flex-1"></span><span
                            class="h-px flex-1"></span></div>
                    <div class="font-display text-[clamp(16px,1.6vw,20px)] font-bold tracking-body text-white">
                        {{ $card2Label }}</div>
                    <div class="mt-auto pt-[clamp(48px,6vw,72px)] flex items-end gap-[clamp(16px,2vw,28px)]">
                        <span
                            class="inline-flex items-center gap-2 flex-none font-display text-[clamp(13px,1.2vw,16px)] font-bold tracking-[-0.01em] pb-[10px]">{{ $card2Badge }}</span>
                        <span
                            class="ml-auto font-display text-[clamp(48px,6.4vw,84px)] font-extrabold tracking-display leading-[0.9] tabular-nums inline-flex items-baseline whitespace-nowrap">{{ $card2Target }}</span>
                    </div>
                </div>
            </div>
            <div class="stats-grid-spacer col-span-6" aria-hidden="true"></div>
        </div>
    </div>
</section>
