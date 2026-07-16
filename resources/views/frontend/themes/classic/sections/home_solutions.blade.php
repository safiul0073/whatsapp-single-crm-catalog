@php
    $d = $section->data ?? [];
    $eyebrowText = $d['eyebrow_text'] ?? __('Solutions');
    $headingLineOne = $d['heading_line_one'] ?? __('One team,');
    $headingHighlight = $d['heading_highlight'] ?? __('six product shapes.');
    $subheading = $d['subheading'] ?? __('Whatever the model — SaaS, marketplace, mobile, fintech — we ship the right architecture for the way it actually has to grow.');
    $categoryItems = collect($projectCategories ?? [])->values();
    $categoryCount = $categoryItems->count();
    $eyebrowCount = str_pad($categoryCount, 2, '0', STR_PAD_LEFT);
    $accentColors = ['sol3-banner--green', 'sol3-banner--blue', 'sol3-banner--blue', 'sol3-banner--green', 'sol3-banner--navy', 'sol3-banner--navy'];
    $numberColors = ['text-brand-green', 'text-brand-blue', 'text-brand-blue', 'text-brand-green', 'text-brand-navy-ink', 'text-brand-navy-ink'];
@endphp

<section class="relative pt-[72px] lg:pt-[120px] pb-10 bg-white overflow-hidden isolate"
    aria-labelledby="sol3-heading">
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
        <div class="absolute left-0 right-0 top-[960px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[1120px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[1280px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[1440px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[1600px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[1760px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[1920px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[2080px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[2240px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
        <div class="absolute left-0 right-0 top-[2400px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]">
        </div>
    </div>
    <div class="section-container relative z-1 flex flex-col gap-10 lg:gap-16">
        <div class="max-w-[720px]">
            <span
                class="inline-flex items-center gap-2 px-3 py-1.5 pl-2.5 bg-white/70 border border-tint-navy rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue"><span
                    class="w-1.5 h-1.5 rounded-pill bg-brand-blue shadow-sol-eyebrow-dot"></span>{{ $eyebrowText }} ·
                {{ $eyebrowCount }}</span>
            <h2 id="sol3-heading"
                class="flex flex-col font-display text-[clamp(36px,5vw,64px)] font-extrabold tracking-display leading-none text-brand-navy-ink mt-[18px]">
                <span>{{ $headingLineOne }}</span><span class="bg-grad-mark bg-clip-text text-transparent">{{ $headingHighlight }}</span>
            </h2>
            <p class="mt-[14px] text-[15.5px] leading-[1.65] text-text-muted max-w-[56ch]">
                {{ $subheading }}
            </p>
        </div>

        @if ($categoryItems->isNotEmpty())
            <div class="flex flex-col gap-5 lg:gap-8">
                @foreach ($categoryItems as $index => $category)
                    @php
                        $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                        $colorClass = $accentColors[$index % count($accentColors)];
                        $numColor = $numberColors[$index % count($numberColors)];
                        $isInverted = $index % 2 !== 0;
                        $firstProject = $category->projects->first();
                        $metrics = $firstProject?->metrics() ?? [];
                        $firstMetric = $metrics[0] ?? null;
                        $tags = $category->projects->take(5)->pluck('name');
                        $eyebrow = $firstProject?->excerpt ?? $category->description ?? '';
                        $imageSlot = $category->media?->url ?? 'assets/images/sections/solutions/' . ($index + 1) . '.webp';
                    @endphp
                    <article
                        class="sol3-banner {{ $colorClass }}{{ $isInverted ? ' is-inverted' : '' }} relative grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-8 lg:gap-16 items-center p-5 lg:p-8 rounded-[28px] border overflow-hidden opacity-0 translate-y-6"
                        data-sol-reveal>
                        <span
                            class="sol3-banner-block sol3-banner-block--a absolute rounded-[4px] pointer-events-none top-[14%] right-[6%] w-[8%] aspect-square bg-[rgba(33,72,255,0.06)] border border-[rgba(33,72,255,0.14)]"></span>
                        <span
                            class="sol3-banner-block sol3-banner-block--b absolute rounded-[4px] pointer-events-none bottom-[12%] right-[18%] w-[5%] aspect-square bg-[rgba(22,199,132,0.1)] border border-[rgba(22,199,132,0.2)]"></span>
                        <div class="sol3-banner-text relative z-1 flex flex-col gap-[18px]">
                            <div class="flex items-center gap-[14px] flex-wrap">
                                <span
                                    class="font-mono text-xs font-bold tracking-[0.18em] {{ $numColor }} tabular-nums">{{ $num }}</span>
                                <span class="w-[22px] h-px bg-current opacity-30"></span>
                                @if ($eyebrow)
                                    <span
                                        class="sol3-banner-eyebrow inline-flex items-center gap-2 font-mono text-micro font-semibold tracking-eyebrow uppercase text-text-muted"><i
                                            data-lucide="sparkles" style="width: 11px; height: 11px"></i>{{ $eyebrow }}</span>
                                @endif
                            </div>
                            <h3
                                class="font-display text-[clamp(28px,3.4vw,44px)] font-extrabold tracking-heading leading-tight-display text-brand-navy-ink m-0 text-balance">
                                {{ $category->name }}</h3>
                            @if ($category->description)
                                <p class="text-[15px] leading-[1.7] text-text-muted m-0 max-w-[56ch]">
                                    {{ $category->description }}
                                </p>
                            @endif
                            @if ($tags->isNotEmpty())
                                <div class="flex flex-wrap gap-1.5 mt-1">
                                    @foreach ($tags as $tag)
                                        <span
                                            class="font-mono text-micro font-medium text-text-muted bg-white/72 border border-[rgba(15,15,73,0.1)] px-2.5 py-[5px] rounded-sm">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <div
                                class="sol3-banner-foot flex items-center justify-between gap-[18px] pt-[18px] border-t border-[rgba(15,15,73,0.08)] flex-wrap">
                                <a href="#contact"
                                    class="sol3-banner-cta inline-flex items-center gap-2 w-fit text-sm font-semibold text-white bg-brand-navy-ink px-[18px] py-3 rounded-md no-underline">Discuss
                                    this build <i data-lucide="arrow-right" style="width: 14px; height: 14px"></i></a>
                                @if ($firstMetric)
                                    <div class="flex flex-col items-end gap-1">
                                        <span
                                            class="bg-grad-mark bg-clip-text text-transparent font-display text-[clamp(28px,3.2vw,40px)] font-extrabold tracking-display leading-none">{{ $firstMetric['value'] }}</span><span
                                            class="font-mono text-[10.5px] font-semibold tracking-[0.14em] uppercase text-text-light">{{ $firstMetric['label'] }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div
                            class="sol3-banner-visual relative z-1 flex items-stretch justify-center min-h-[200px] lg:min-h-[320px]">
                            <div class="absolute inset-0 rounded-[22px] overflow-hidden z-0 shadow-sol-photo">
                                <img src="{{ $imageSlot }}" alt="" loading="lazy"
                                    class="w-full h-full object-cover object-center block saturate-[0.95] brightness-[0.98]" /><span
                                    class="sol3-banner-photo-tint absolute inset-0"></span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
