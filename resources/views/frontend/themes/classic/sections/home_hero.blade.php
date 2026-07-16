    @php
        $d = $section->data ?? [];
        $eyebrowBadgeText = $d['eyebrow_badge_text'] ?? __('Live');
        $eyebrowMessage = $d['eyebrow_message'] ?? __('Now booking Q3 builds — 2 slots left');
        $headingLineOne = $d['heading_line_one'] ?? __('We build scalable');
        $headingAccent = $d['heading_accent'] ?? __('SaaS, web and mobile');
        $headingLineTwo = $d['heading_line_two'] ?? __('products.');
        $captionPrefix = $d['caption_prefix'] ?? __('We ship');
        $captionWords = array_filter(
            array_map('trim', explode("\n", $d['caption_words'] ?? __("SaaS platforms\nweb apps\nmobile apps\nMVPs"))),
        );
        $subheading =
            $d['subheading'] ??
            __('Classic is a senior product team for founders and operators. From discovery to launch — clean UI, scalable code, one timeline.');
        $primaryCtaText = $d['primary_cta_text'] ?? __('Start a Project');
        $primaryCtaLink = $d['primary_cta_link'] ?? '#contact';
        $secondaryCtaText = $d['secondary_cta_text'] ?? __('Watch Showreel');
        $secondaryCtaLink = $d['secondary_cta_link'] ?? '#showreel';
        $stat1Value = $d['stat_1_value'] ?? '120+';
        $stat1Label = $d['stat_1_label'] ?? __('Products shipped');
        $stat2Value = $d['stat_2_value'] ?? '8 wks';
        $stat2Label = $d['stat_2_label'] ?? __('Average MVP');
        $stat3Value = $d['stat_3_value'] ?? '4.9/5';
        $stat3Label = $d['stat_3_label'] ?? __('Client rating');
        $uptimeChipText = $d['uptime_chip_text'] ?? __('99.98% uptime');
        $deployChipVersion = $d['deploy_chip_version'] ?? 'v2.4.1 · production';
        $marqueeEyebrow = $d['marquee_eyebrow'] ?? __('Trusted by teams that ship at');
    @endphp
    <section class="hero relative isolate overflow-hidden bg-bg-soft border-b border-border-default pt-24 pb-0 -mt-28">
        <!-- Dashed grid lines -->
        <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_78%,transparent_100%)]"
            aria-hidden="true">
            <!-- Vertical dashed columns @ 25%, 50%, 75% -->
            <div class="absolute top-0 bottom-0 left-1/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
            <div class="absolute top-0 bottom-0 left-1/2 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
            <div class="absolute top-0 bottom-0 left-3/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
            <!-- Horizontal dashed rows every 160px -->
            <div class="absolute left-0 right-0 top-[160px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
            </div>
            <div class="absolute left-0 right-0 top-[320px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
            </div>
            <div class="absolute left-0 right-0 top-[480px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
            </div>
            <div class="absolute left-0 right-0 top-[640px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
            </div>
            <div class="absolute left-0 right-0 top-[800px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
            </div>
        </div>

        <!-- Architectural tinted blocks snapped to dashed grid -->
        <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_78%,transparent_100%)]"
            aria-hidden="true">
            <span class="absolute bg-tint-blue top-0 left-1/4 w-1/4 h-[160px]"></span>
            <span class="absolute bg-tint-green/60 top-[160px] right-0 w-1/4 h-[160px]"></span>
            <span class="absolute bg-tint-blue/70 top-[320px] left-0 w-1/4 h-[160px]"></span>
            <span class="absolute bg-tint-blue top-[480px] left-1/2 w-1/4 h-[160px]"></span>
        </div>

        <div
            class="hero__content section-container relative z-1 grid items-center gap-12 pt-14 lg:grid-cols-2 lg:gap-16 xl:grid-cols-5 xl:gap-24">
            <!-- COPY -->
            <div class="xl:col-span-3">
                <!-- Eyebrow chip -->
                <div
                    class="hero-fade hero-fade-1 inline-flex items-center gap-2.5 rounded-pill border border-border-soft bg-white/70 py-1.5 pl-2 pr-3 backdrop-blur-md shadow-hero-eyebrow">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-pill bg-success-soft px-2.5 py-1 text-micro font-bold uppercase tracking-eyebrow text-brand-green">
                        <span class="animate-hero-pulse h-1.5 w-1.5 rounded-pill bg-brand-green"></span>
                        {{ $eyebrowBadgeText }}
                    </span>
                    <span class="text-caption font-medium text-text-default">{{ $eyebrowMessage }}</span>
                    <span class="text-text-light"><i data-lucide="arrow-right" class="h-3 w-3"
                            aria-hidden="true"></i></span>
                </div>

                <!-- Headline -->
                @php
                    $lineOneWords = array_values(array_filter(explode(' ', $headingLineOne)));
                    $accentWords = array_values(array_filter(explode(' ', $headingAccent)));
                    $lineTwoWords = array_values(array_filter(explode(' ', $headingLineTwo)));
                    $allWords = array_merge($lineOneWords, $accentWords, $lineTwoWords);
                    $baseDelay = 220;
                    $accentStart = count($lineOneWords);
                    $accentEnd = $accentStart + count($accentWords) - 1;
                    $lastAccentIdx = $accentEnd;
                    $ariaLabel = $headingLineOne . ' ' . $headingAccent . ' ' . $headingLineTwo;
                @endphp
                <h1 aria-label="{{ $ariaLabel }}"
                    class="mt-6 max-w-[15ch] overflow-visible font-display text-[40px] font-extrabold leading-tight-display tracking-display text-balance text-brand-navy-ink md:text-[52px] lg:text-[64px] xl:text-[76px]">
                    @foreach ($allWords as $i => $word)
                        @php
                            $delay = $baseDelay + $i * 70;
                            $isAccent = $i >= $accentStart && $i <= $accentEnd;
                            $isLastAccent = $i === $lastAccentIdx;
                        @endphp
                        @if ($isLastAccent)
                            <span class="hero-word hero-word--accent hero-word--underline"><span
                                    class="hero-word__inner"
                                    style="--rise-delay: {{ $delay }}ms">{{ $word }}</span><svg
                                    viewBox="0 0 220 14" preserveAspectRatio="none" aria-hidden="true"
                                    class="hero-underline-svg">
                                    <path d="M2 8 C 60 1, 160 1, 218 8" stroke="#2148FF" stroke-width="3"
                                        stroke-linecap="round" fill="none" pathLength="1"
                                        style="animation-delay: {{ $delay + 420 }}ms" />
                                </svg>
                            </span>
                        @elseif ($isAccent)
                            <span class="hero-word hero-word--accent"><span class="hero-word__inner"
                                    style="--rise-delay: {{ $delay }}ms">{{ $word }}</span></span>
                        @else
                            <span class="hero-word"><span class="hero-word__inner"
                                    style="--rise-delay: {{ $delay }}ms">{{ $word }}</span></span>
                        @endif
                    @endforeach
                </h1>

                <!-- Rotating caption -->
                <p class="hero-caption" aria-live="polite" data-hero-caption>
                    <span class="hero-caption__prefix">{{ $captionPrefix }}</span>
                    <span class="hero-caption__clip">
                        @foreach ($captionWords as $index => $captionWord)
                            <span class="hero-caption__word {{ $index === 0 ? 'hero-caption__word--in' : '' }}"
                                data-hero-caption-word>{{ $captionWord }}</span>
                        @endforeach
                    </span>
                    <span class="ml-[-0.2em] text-brand-navy-ink" aria-hidden="true">.</span>
                </p>

                <!-- Sub -->
                <p class="hero-fade hero-fade-3 mt-4 max-w-xl font-body text-body-lg leading-body text-text-muted">
                    {{ $subheading }}
                </p>

                <!-- CTAs -->
                <div class="hero-fade hero-fade-4 mt-9 flex flex-wrap gap-3">
                    <a href="{{ $primaryCtaLink }}"
                        class="hero-cta-primary inline-flex items-center gap-2.5 rounded-md border border-white/15 bg-gradient-to-b from-brand-blue to-primary-hover px-5.5 py-3.75 text-sm font-semibold text-white shadow-hero-cta transition-all duration-200">
                        {{ $primaryCtaText }}
                        <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
                    </a>
                    <a href="{{ $secondaryCtaLink }}"
                        class="hero-cta-ghost inline-flex items-center gap-2.5 rounded-md border border-border-default bg-white px-5 py-3.5 text-sm font-semibold text-text-strong transition-all duration-200">
                        <span class="grid h-5.5 w-5.5 place-items-center rounded-pill bg-brand-navy-ink text-white">
                            <i data-lucide="play" class="h-2.5 w-2.5" fill="currentColor" stroke-width="0"
                                aria-hidden="true"></i>
                        </span>
                        {{ $secondaryCtaText }}
                    </a>
                </div>

                <!-- Trust rail -->
                <div class="hero-fade hero-fade-5 mt-12 grid max-w-lg grid-cols-3 items-center gap-8">
                    <div class="transition-transform duration-200 hover:-translate-y-px">
                        <div
                            class="font-display text-[26px] font-extrabold leading-none tracking-heading text-brand-navy-ink">
                            {{ $stat1Value }}</div>
                        <div class="mt-1.5 text-[12.5px] text-text-muted">{{ $stat1Label }}</div>
                    </div>
                    <div class="transition-transform duration-200 hover:-translate-y-px">
                        <div
                            class="font-display text-[26px] font-extrabold leading-none tracking-heading text-brand-navy-ink">
                            {{ $stat2Value }}</div>
                        <div class="mt-1.5 text-[12.5px] text-text-muted">{{ $stat2Label }}</div>
                    </div>
                    <div class="transition-transform duration-200 hover:-translate-y-px">
                        <div
                            class="font-display text-[26px] font-extrabold leading-none tracking-heading text-brand-navy-ink">
                            {{ $stat3Value }}</div>
                        <div class="mt-1.5 text-[12.5px] text-text-muted">{{ $stat3Label }}</div>
                    </div>
                </div>
            </div>

            <!-- VISUAL -->
            <div class="hero-fade hero-fade-3 hero-stage relative grid h-[500px] place-items-center isolate lg:h-[600px] xl:col-span-2"
                data-hero-stage>
                <div class="absolute inset-[6%] rounded-full pointer-events-none z-0 bg-grad-halo blur-[2px]"
                    aria-hidden="true"></div>

                <img class="absolute pointer-events-none select-none top-1/2 left-1/2 w-[92%] aspect-square object-contain will-change-transform z-1 animate-hero-bg-breathe"
                    style="--bx: 0px; --by: 0px; transform: translate(calc(-50% + var(--bx)), calc(-50% + var(--by))) scale(1)"
                    src="assets/images/hero/hero-circle-bg.webp" alt="" aria-hidden="true" loading="eager"
                    decoding="async" data-hero-bg />
                <img class="absolute pointer-events-none select-none top-1/2 left-1/2 w-[104%] aspect-square object-contain will-change-transform z-2 animate-hero-ring-spin"
                    style="--rx: 0px; --ry: 0px; transform: translate(calc(-50% + var(--rx)), calc(-50% + var(--ry))) rotate(0deg)"
                    src="assets/images/hero/hero-stech-rotate.webp" alt="" aria-hidden="true" loading="eager"
                    decoding="async" data-hero-ring />
                <img class="absolute pointer-events-none select-none top-1/2 left-1/2 w-[58%] aspect-square object-contain will-change-transform z-3 animate-hero-rocket-float drop-shadow-[0_30px_40px_rgba(15,15,73,0.18)]"
                    style="--mx: 0px; --my: 0px; transform: translate(calc(-50% + var(--mx)), calc(-50% + var(--my))) translateY(0)"
                    src="assets/images/hero/hero-rocket-center.webp" alt="Classic — products that take off"
                    loading="eager" decoding="async" data-hero-rocket />

                <!-- Uptime chip -->
                <div class="absolute z-4 inline-flex items-center gap-2.5 rounded-pill border border-border-soft bg-white/90 px-3.5 py-2 font-mono text-xs font-semibold text-brand-navy-ink backdrop-blur-md shadow-hero-chip will-change-transform top-[9%] left-0 animate-hero-float-mid"
                    aria-hidden="true">
                    <span
                        class="rounded-pill bg-brand-green w-[7px] h-[7px] animate-hero-pulse shadow-hero-dot"></span>
                    {{ $uptimeChipText }}
                </div>

                <!-- Deploy chip -->
                <div class="absolute z-4 inline-flex items-center gap-2.5 rounded-pill border border-[rgba(255,255,255,0.08)] bg-brand-navy-ink text-white px-3.5 py-2.5 font-mono text-xs font-semibold backdrop-blur-md shadow-hero-deploy will-change-transform bottom-[8%] right-0 animate-hero-float-slow"
                    aria-hidden="true">
                    <span
                        class="rounded-sm grid place-items-center flex-none w-6 h-6 bg-gradient-to-br from-brand-green to-brand-green-deep">
                        <i data-lucide="check" class="h-2.5 w-2.5 text-white" stroke-width="3"
                            aria-hidden="true"></i>
                    </span>
                    <span class="flex flex-col leading-[1.15]">
                        <span class="font-body text-[10.5px] font-medium tracking-[.02em] text-text-light">{{ __('Deploy successful') }}</span>
                        <span class="text-[12.5px] font-semibold tracking-[-.01em]">{{ $deployChipVersion }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Logo marquee -->
        @php
            $logos = collect($d['logos'] ?? [])
                ->map(
                    fn($item) => [
                        'url' => media_url($item['logo_media_id'] ?? null) ?? ($item['fallback_src'] ?? null),
                        'alt' => $item['alt'] ?? '',
                    ],
                )
                ->filter(fn($item) => !empty($item['url']))
                ->values()
                ->toArray();
        @endphp

        <div class="hero-fade hero-fade-5 hero-marquee-section relative mt-[clamp(32px,5vw,88px)] py-8">
            <p class="text-center font-mono text-micro font-semibold tracking-eyebrow uppercase text-text-light mb-6">
                {{ $marqueeEyebrow }}</p>
            <div class="hero-marquee" role="region" aria-label="{{ __('Featured clients') }}">
                <div class="hero-marquee__track" aria-hidden="false" data-hero-marquee-track>
                    @foreach ($logos as $logo)
                        <span class="hero-marquee__item"><img src="{{ $logo['url'] }}" alt="{{ $logo['alt'] }}"
                                class="h-7 w-auto" loading="lazy" /></span>
                    @endforeach
                </div>
                <div class="hero-marquee__track" aria-hidden="true" data-hero-marquee-track>
                    @foreach ($logos as $logo)
                        <span class="hero-marquee__item"><img src="{{ $logo['url'] }}" alt=""
                                class="h-7 w-auto" loading="lazy" /></span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
