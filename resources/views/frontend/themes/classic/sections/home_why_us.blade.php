@php
    $d = $section->data ?? [];

    $eyebrowText   = $d['eyebrow_text']   ?? __('Why Classic');
    $headingOne    = $d['heading_line_one'] ?? __('Built like a senior product team.');
    $headingTwo    = $d['heading_line_two'] ?? __('Priced like an honest one.');
    $subheading    = $d['subheading']      ?? __("What separates Classic is what we don't do — no junior handoffs, no locked code, no quarter-long timelines, no surprise invoices.");
    $pillars       = $d['pillars']         ?? [];
    $footerText    = $d['footer_text']     ?? __("Sound like the team you'd want to build with?");
    $footerEmail   = $d['footer_email']    ?? 'support.com';
    $footerEmailLabel = $d['footer_email_label'] ?? __('24/7 Available');
    $footerCtaText = $d['footer_cta_text'] ?? __('Book a 30-min scope call');
    $footerCtaLink = $d['footer_cta_link'] ?? '#contact';

    $colorNumClass = [
        'blue'  => 'text-brand-blue',
        'green' => 'text-brand-green',
        'navy'  => 'text-brand-navy-ink',
    ];
    $checkmarkBgClass = [
        'blue'  => 'bg-brand-blue/12 text-brand-blue',
        'green' => 'bg-brand-green/12 text-brand-green',
        'navy'  => 'bg-brand-blue/12 text-brand-blue',
    ];
    $colSpanClass = [
        'hero' => 'col-span-4',
        'std'  => 'col-span-2',
        'wide' => 'col-span-4',
    ];

    $delays = [0, 80, 160, 240, 0, 80, 160];
@endphp

<section class="relative py-[72px] md:py-[96px] xl:py-[120px] bg-bg-section overflow-hidden isolate"
    aria-labelledby="why2-heading">
    <!-- Dashed grid lines -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_78%,transparent_100%)]"
        aria-hidden="true">
        <div class="absolute top-0 bottom-0 left-1/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-1/2 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-3/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[160px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[320px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[480px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[640px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[800px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[960px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[1120px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
    </div>
    <div class="section-container relative z-10 flex flex-col gap-10 lg:gap-[72px]">
        <div class="max-w-[720px] mx-auto text-center">
            <span
                class="inline-flex items-center gap-2 py-1.5 px-3 pl-2.5 bg-white/70 border border-border-soft rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue"><span
                    class="why2-eyebrow-dot w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>{{ $eyebrowText }}</span>
            <h2 id="why2-heading"
                class="flex flex-col font-display text-[34px] md:text-[46px] xl:text-[56px] font-extrabold tracking-heading leading-[1.06] text-brand-navy-ink mt-[18px] text-balance">
                <span>{{ $headingOne }}</span><span
                    class="bg-grad-mark bg-clip-text text-transparent">{{ $headingTwo }}</span>
            </h2>
            <p class="mt-4 mx-auto text-[15.5px] leading-[1.65] text-text-muted max-w-[56ch]">
                {{ $subheading }}
            </p>
        </div>

        <div class="why2-pillars grid grid-cols-6 gap-[14px] xl:gap-5 items-stretch">
            @foreach ($pillars as $index => $pillar)
                @php
                    $color    = $pillar['color'] ?? 'blue';
                    $size     = $pillar['size']  ?? 'std';
                    $numClass = $colorNumClass[$color] ?? 'text-brand-blue';
                    $span     = $colSpanClass[$size] ?? 'col-span-2';
                    $delay    = $delays[$index] ?? 0;
                    $isHero   = $size === 'hero';
                    $isWide   = $size === 'wide';
                    $hasChecklist = ($isHero || $isWide) && !empty($pillar['checklist']);
                    $checkItems = $hasChecklist
                        ? array_filter(array_map('trim', explode("\n", $pillar['checklist'] ?? '')))
                        : [];
                    $checkBg = $checkmarkBgClass[$color] ?? 'bg-brand-blue/12 text-brand-blue';
                    $padding = $isHero ? 'p-6 md:p-8 xl:p-[44px]' : 'p-6 md:p-8 xl:p-[34px]';
                @endphp
                <article
                    class="why2-pillar why2-pillar--{{ $color }} why2-pillar--{{ $size }} {{ $span }} relative {{ $padding }} border border-border-soft rounded-[22px] flex flex-col gap-3.5 overflow-hidden"
                    data-why-reveal style="--reveal-delay: {{ $delay }}ms">
                    <svg class="why2-pillar-corner absolute right-4 bottom-4 w-9 h-9 text-brand-navy-ink/10 pointer-events-none"
                        viewBox="0 0 60 60">
                        <path d="M60 0 L60 22 L38 22 L38 38 L22 38 L22 60" fill="none" stroke="currentColor"
                            stroke-width="1" />
                    </svg>
                    <div class="flex items-center justify-between">
                        <span
                            class="why2-pillar-num font-mono text-[11.5px] font-semibold tracking-[0.14em] {{ $numClass }}">{{ $pillar['number'] ?? '' }}</span><span
                            class="why2-pillar-icon {{ $isHero ? 'w-12 h-12 rounded-md' : 'w-10 h-10 rounded-[10px]' }} border border-border-default bg-white text-text-muted inline-grid place-items-center"><i
                                class="ph {{ $pillar['icon'] ?? 'ph-star' }} text-[18px]"></i></span>
                    </div>
                    <h3
                        class="why2-pillar-title font-display {{ $isHero ? 'text-[22px] md:text-[26px] xl:text-[30px]' : ($isWide ? 'text-[20px] md:text-[23px] xl:text-[26px]' : 'text-[18px] md:text-[20px] xl:text-[22px]') }} font-extrabold tracking-heading leading-[1.18] text-brand-navy-ink m-0 text-balance">
                        {{ $pillar['title'] ?? '' }}
                    </h3>
                    <p class="{{ $isHero ? 'text-[15px]' : 'text-body-sm' }} leading-[1.65] text-text-muted m-0 {{ $isHero ? 'max-w-[56ch]' : ($isWide ? 'text-[14.5px] max-w-[64ch]' : '') }}">{{ $pillar['description'] ?? '' }}</p>

                    @if ($hasChecklist && count($checkItems) > 0)
                        <ul class="list-none p-0 pt-4 mt-auto border-t border-border-soft grid grid-cols-2 gap-2.5 gap-x-6 {{ $isWide ? 'why2-pillar-checks' : '' }}">
                            @foreach ($checkItems as $item)
                                <li class="flex items-center gap-2.5 text-[13.5px] text-text-strong">
                                    <span
                                        class="why2-pillar-checks-mark flex-none w-[18px] h-[18px] rounded-pill {{ $checkBg }} inline-grid place-items-center"><i
                                            data-lucide="check" class="w-[11px] h-[11px]" stroke-width="2.6"></i></span>{{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    @elseif (!empty($pillar['stat_value']))
                        <div class="mt-auto pt-4 border-t border-border-soft flex items-baseline gap-2.5">
                            <span class="font-display text-body font-bold tracking-body leading-[1.1] text-brand-navy-ink">{{ $pillar['stat_value'] }}</span><span
                                class="font-mono text-[10.5px] font-semibold tracking-[0.14em] uppercase text-text-light">{{ $pillar['stat_label'] ?? '' }}</span>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="why2-foot relative flex items-stretch rounded-[18px] overflow-hidden min-h-[88px]">
            <!-- Blue left panel (~60%) -->
            <div class="why2-foot-left flex items-center px-8 md:px-12 py-6 flex-1 min-w-0">
                <span
                    class="font-display text-[15px] md:text-[17px] xl:text-body-lg font-bold tracking-body text-white">{{ $footerText }}</span>
            </div>
            <!-- Dark right panel (~40%) -->
            <div class="why2-foot-right flex items-center gap-6 pl-10 pr-8 md:pl-12 md:pr-10 py-6 flex-shrink-0">
                <!-- Mail icon + text -->
                <div class="flex items-center gap-3">
                    <span
                        class="w-9 h-9 rounded-full bg-brand-blue flex-shrink-0 inline-flex items-center justify-center">
                        <i data-lucide="mail" class="w-[15px] h-[15px] text-white"></i>
                    </span>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[11.5px] font-medium text-white/50 leading-none">{{ $footerEmailLabel }}</span>
                        <span class="text-[13.5px] font-bold text-white leading-tight">{{ $footerEmail }}</span>
                    </div>
                </div>
                <!-- Divider -->
                <span class="w-px h-8 bg-white/15 flex-shrink-0 hidden md:block"></span>
                <!-- CTA -->
                <a href="{{ $footerCtaLink }}"
                    class="why2-foot-cta inline-flex items-center gap-2 text-[13px] font-semibold text-text-strong bg-white py-2.5 px-5 rounded-pill no-underline whitespace-nowrap flex-shrink-0">
                    {{ $footerCtaText }} <i data-lucide="arrow-right" class="w-[13px] h-[13px]"></i>
                </a>
            </div>
        </div>
    </div>
</section>
