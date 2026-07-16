@php
    $d = $section->data ?? [];

    $eyebrowText = $d['eyebrow_text'] ?? __('About our company');
    $estText = $d['est_text'] ?? __('EST · 2014 · SOFTIVUS');
    $headingLineOne = $d['heading_line_one'] ?? __('We build complete');
    $headingAccent = $d['heading_accent'] ?? __('SaaS, web, and mobile');
    $headingLineTwo = $d['heading_line_two'] ?? __('products — end to end.');
    $introBody =
        $d['intro_body'] ??
        __('Classic is a senior product team for founders and operators. From discovery to launch, we ship modern digital products with clean UI, scalable code, and a single delivery timeline.');
    $videoCardImageUrl = media_url($d['video_card_image_media_id'] ?? null);
    $videoLabel = $d['video_label'] ?? __('Showreel · 2 min');
    $videoTitle = $d['video_title'] ?? __('How we ship products');
    $videoYear = $d['video_year'] ?? '2024';
    $videoLink = $d['video_link'] ?? '';
    $badgeNumber = $d['badge_number'] ?? '10+';
    $archImageUrl = media_url($d['arch_image_media_id'] ?? null);
    $archCaptionTitle = $d['arch_caption_title'] ?? __('A team that owns the outcome.');
    $archCaptionBody =
        $d['arch_caption_body'] ??
        __('Same senior engineers and designers from kickoff to launch — no junior handoffs.');
    $bodyParagraph1 =
        $d['body_paragraph_1'] ??
        __('We focus on smart, efficient, high-performance digital products. Strong expertise across product strategy, UI/UX, web and mobile engineering, and platform integrations — combined into one team that owns delivery from kickoff to ship.');
    $bodyParagraph2 =
        $d['body_paragraph_2'] ??
        __('Our work blends product strategy and engineering taste so the outcome looks the way it should and performs the way it must — for founders, operators, and growing companies.');
    $differentiatorsHeading = $d['differentiators_heading'] ?? __('What makes us different');
    $differentiators = $d['differentiators'] ?? [
        ['label' => __('Senior product team')],
        ['label' => __('Engineering taste')],
        ['label' => __('120+ products shipped')],
        ['label' => __('Remote-first, global')],
    ];
    $ctaText = $d['cta_text'] ?? __('About Classic');
    $ctaLink = $d['cta_link'] ?? '/about';
@endphp
<section class="aboutv2 relative py-[clamp(72px,10vw,120px)] bg-white overflow-hidden isolate"
    aria-labelledby="aboutv2-heading">
    <!-- Dashed grid lines -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_78%,transparent_100%)]"
        aria-hidden="true">
        <div class="absolute top-0 bottom-0 left-1/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-1/2 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-3/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
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

    <div class="section-container relative z-[1] flex flex-col gap-[clamp(40px,5vw,64px)]">
        <!-- Eyebrow row -->
        <div class="flex items-center justify-between gap-4 max-sm:flex-col max-sm:items-start">
            <span
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/70 border border-border-soft rounded-full text-[11.5px] font-semibold tracking-eyebrow uppercase text-brand-blue backdrop-blur-sm">
                <span class="aboutv2-eyebrow-pulse w-1.5 h-1.5 rounded-full bg-brand-blue"></span>
                {{ $eyebrowText }}
            </span>
            <span class="font-mono text-micro tracking-eyebrow text-text-light">{{ $estText }}</span>
        </div>

        <!-- Top: heading + metric card -->
        <div
            class="grid [grid-template-columns:minmax(0,1.1fr)_minmax(min(320px,100%),420px)] max-[1100px]:[grid-template-columns:1fr] gap-[clamp(40px,5vw,80px)] items-start">
            <div>
                <h2 id="aboutv2-heading"
                    class="font-display text-[clamp(32px,4.4vw,56px)] font-extrabold tracking-display leading-[1.08] text-brand-navy-ink m-0 text-balance">
                    {{ $headingLineOne }} <span class="aboutv2-accent">{{ $headingAccent }}</span>
                    {{ $headingLineTwo }}
                </h2>
                <p class="mt-[22px] text-body leading-relaxed-body text-text-default max-w-[60ch]">
                    {{ $introBody }}
                </p>
            </div>

            <button type="button" aria-label="{{ __('Play showreel video') }}" data-video-trigger @if ($videoLink)
            data-video-src="{{ $videoLink }}" @endif
                class="group aboutv2-video-card relative block overflow-hidden rounded-[18px] border border-border-soft shadow-xl aspect-[4/3] bg-brand-navy-ink cursor-pointer w-full text-left p-0">
                @if ($videoCardImageUrl)
                    <img src="{{ $videoCardImageUrl }}" alt="Classic team at work" loading="lazy"
                        class="absolute inset-0 w-full h-full object-cover saturate-[0.95] brightness-[0.85] transition-transform duration-[600ms] [transition-timing-function:var(--ease-out-soft)] group-hover:scale-105" />
                @else
                    <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1200&q=80"
                        alt="Classic team at work" loading="lazy"
                        class="absolute inset-0 w-full h-full object-cover saturate-[0.95] brightness-[0.85] transition-transform duration-[600ms] [transition-timing-function:var(--ease-out-soft)] group-hover:scale-105" />
                @endif
                <span
                    class="absolute inset-0 bg-gradient-to-t from-brand-navy-ink/70 via-brand-navy-ink/10 to-transparent"></span>

                <!-- Play button -->
                <span class="absolute inset-0 grid place-items-center">
                    <span
                        class="relative inline-grid place-items-center w-[72px] h-[72px] rounded-full bg-white/95 text-brand-navy-ink shadow-[0_18px_48px_-8px_rgba(15,15,73,0.5)] transition-transform duration-[260ms] [transition-timing-function:var(--ease-out-soft)] group-hover:scale-110">
                        <span class="absolute inset-0 rounded-full bg-white/40 animate-hero-pulse"></span>
                        <i data-lucide="play" class="relative w-5 h-5 ml-1" fill="currentColor" stroke-width="0"
                            aria-hidden="true"></i>
                    </span>
                </span>

                <!-- Caption -->
                <span class="absolute bottom-0 left-0 right-0 p-5 flex items-center justify-between gap-3 text-white">
                    <span class="flex flex-col gap-0.5">
                        <span
                            class="inline-flex items-center gap-[7px] text-[10.5px] font-semibold tracking-eyebrow uppercase text-white/80">
                            <span
                                class="w-1.5 h-1.5 rounded-full bg-brand-green shadow-[0_0_0_3px_rgba(22,199,132,0.25)]"></span>
                            {{ $videoLabel }}
                        </span>
                        <span class="font-display text-base font-bold tracking-body text-white">{{ $videoTitle }}</span>
                    </span>
                    <span
                        class="font-mono text-[10.5px] font-semibold tracking-eyebrow uppercase text-white/70 bg-white/10 backdrop-blur-sm border border-white/15 px-2 py-[3px] rounded-full">{{ $videoYear }}</span>
                </span>
            </button>
        </div>

        <!-- Mid: architecture card + body copy -->
        <div
            class="grid [grid-template-columns:minmax(min(360px,100%),0.95fr)_minmax(0,1fr)] max-[1100px]:[grid-template-columns:1fr] gap-[clamp(40px,5vw,80px)] items-stretch">
            <!-- Architecture card (wrapper allows badge to overhang the corner) -->
            <div class="relative">
                <!-- Rotating expertise badge -->
                <span
                    class="aboutv2-badge absolute -top-[clamp(34px,4.5vw,60px)] max-sm:-top-8 max-xs:-top-6 -left-[clamp(22px,3.5vw,48px)] max-sm:-left-3 max-xs:-left-1.5 z-[3] w-[clamp(116px,13vw,168px)] max-sm:w-[108px] max-xs:w-[96px] aspect-square inline-grid place-items-center select-none"
                    aria-label="{{ __('A decade of expertise') }}">
                    <span
                        class="absolute inset-0 rounded-pill bg-gradient-to-br from-brand-blue to-brand-blue-electric shadow-hero-cta"></span>
                    <svg class="aboutv2-badge-ring absolute inset-0 w-full h-full text-white" viewBox="0 0 200 200"
                        aria-hidden="true">
                        <defs>
                            <path id="aboutv2-badge-circle" d="M 100 28 a 72 72 0 1 1 0 144 a 72 72 0 1 1 0 -144 Z" />
                        </defs>
                        <text
                            class="aboutv2-badge-text fill-current font-display text-[15px] font-bold tracking-[0.22em] uppercase">
                            <textPath href="#aboutv2-badge-circle" startOffset="0">· DECADE OF EXPERTISE · DECADE OF
                                EXPERTISE
                            </textPath>
                        </text>
                    </svg>
                    <span
                        class="relative z-1 text-white font-display font-extrabold tracking-tight leading-none text-[clamp(24px,3vw,34px)] max-sm:text-xl max-xs:text-lg">{{ $badgeNumber }}</span>
                </span>
                <div
                    class="group aboutv2-architecture-card relative overflow-hidden rounded-[18px] border border-border-soft shadow-xl min-h-[260px] sm:min-h-[360px] h-full max-[1100px]:aspect-[16/10]">
                    @if ($archImageUrl)
                        <img src="{{ $archImageUrl }}" alt="Classic team collaborating" loading="lazy"
                            class="absolute inset-0 w-full h-full object-cover transition-transform duration-[600ms] [transition-timing-function:var(--ease-out-soft)] group-hover:scale-105" />
                    @else
                        <img src="https://images.unsplash.com/photo-1551434678-e076c223a692?auto=format&fit=crop&w=1000&q=80"
                            alt="Classic team collaborating" loading="lazy"
                            class="absolute inset-0 w-full h-full object-cover transition-transform duration-[600ms] [transition-timing-function:var(--ease-out-soft)] group-hover:scale-105" />
                    @endif
                    <span
                        class="absolute inset-0 bg-gradient-to-t from-brand-navy-ink/80 via-brand-navy-ink/20 to-transparent"></span>

                    <!-- Bottom caption -->
                    <div class="absolute bottom-0 left-0 right-0 p-5 flex flex-col gap-1.5 text-white">
                        <span
                            class="font-display text-lg font-bold tracking-body text-balance">{{ $archCaptionTitle }}</span>
                        <span class="text-[13px] leading-[1.5] text-white/80 max-w-[40ch]">{{ $archCaptionBody }}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-[18px]">
                <p class="text-[15px] leading-relaxed-body text-text-default m-0 max-w-[64ch]">
                    {{ $bodyParagraph1 }}
                </p>
                <p class="text-[15px] leading-relaxed-body text-text-default m-0 max-w-[64ch]">
                    {{ $bodyParagraph2 }}
                </p>
                <div class="mt-[14px] pt-[22px]">
                    <div
                        class="font-display font-body text-body-sm leading-body text-text-muted font-bold tracking-body text-brand-navy-ink mb-[14px]">
                        {{ $differentiatorsHeading }}
                    </div>
                    <ul class="list-none p-0 m-0 grid grid-cols-2 gap-x-6 gap-y-3 max-sm:grid-cols-1">
                        @foreach ($differentiators as $item)
                            <li
                                class="flex items-center gap-2.5 font-body text-body-sm leading-body text-text-muted text-text-strong">
                                <span
                                    class="shrink-0 w-[22px] h-[22px] rounded-[6px] bg-brand-green/10 inline-grid place-items-center text-brand-green"><i
                                        data-lucide="check" class="size-[13px] [stroke-width:2.4]"></i></span>
                                <span>{{ $item['label'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <a href="{{ $ctaLink }}"
                    class="aboutv2-cta self-start inline-flex items-center gap-2 font-body text-body-sm leading-body text-text-muted font-semibold text-text-on-brand bg-brand-navy-ink px-[18px] py-3 rounded-radius-md no-underline mt-2 transition-[transform,background] duration-[220ms] [transition-timing-function:var(--ease-out-soft)] hover:bg-brand-navy hover:-translate-y-px">
                    {{ $ctaText }} <i data-lucide="arrow-right" class="size-[15px]"></i>
                </a>
            </div>
        </div>
    </div>
</section>
