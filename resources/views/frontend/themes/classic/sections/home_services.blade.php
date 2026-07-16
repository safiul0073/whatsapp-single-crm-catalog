@php
    $d = $section->data ?? [];
    $eyebrowText = $d['eyebrow_text'] ?? __('Services');
    $headingLineOne = $d['heading_line_one'] ?? __('Engineering teams');
    $headingHighlight = $d['heading_highlight'] ?? __('that ship.');
    $ctaText = $d['cta_text'] ?? __('Explore all services');
    $ctaLink = $d['cta_link'] ?? '#services';
    $categoryItems = collect($serviceCategories ?? [])->values();
    $categoryCount = $categoryItems->count();
    $eyebrowCount = str_pad($categoryCount, 2, '0', STR_PAD_LEFT);
    $cardColors = ['srv3-card--blue', 'srv3-card--navy', 'srv3-card--green'];
@endphp

<section id="services" class="relative py-[clamp(72px,9vw,120px)] bg-bg-section overflow-hidden isolate" data-srv
    data-preview-active="false" aria-labelledby="srv3-heading">
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
    </div>
    <img class="srv3-deco absolute top-[18%] left-[-3vw] w-[clamp(140px,14vw,220px)] h-auto z-0 pointer-events-none opacity-85 will-change-transform animate-srv3-deco-float origin-[50%_60%] max-[1100px]:hidden"
        src="assets/images/sections/element_01.webp" alt="" aria-hidden="true" loading="lazy" />

    @if ($categoryItems->isNotEmpty())
        @php $firstCategory = $categoryItems->first(); @endphp
        <div class="srv3-preview absolute top-0 left-0 w-[clamp(220px,22vw,320px)] aspect-[4/5] rounded-lg overflow-hidden pointer-events-none z-5 opacity-0 will-change-transform"
            data-srv-preview aria-hidden="true">
            @foreach ($categoryItems as $category)
                @php $previewService = $category->services->first(); @endphp
                <img class="srv3-preview-img absolute inset-0 w-full h-full object-cover opacity-0 scale-[1.04]"
                    data-srv-preview-img="{{ $category->slug }}"
                    src="{{ $previewService?->media?->url ?? 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=900&q=80' }}"
                    alt="" loading="lazy" />
            @endforeach
            <div class="absolute left-3.5 bottom-3.5 z-1 inline-flex items-center gap-2 px-2.5 py-1.5 bg-white/92 rounded-pill font-mono text-micro font-semibold tracking-eyebrow uppercase text-brand-navy-ink"
                data-srv-preview-tag>
                <span class="text-brand-blue">01</span><span>{{ $firstCategory->slug }}</span>
            </div>
        </div>
    @endif

    <div class="section-container">
        <div
            class="relative z-1 flex items-end justify-between gap-8 mb-[clamp(36px,4vw,56px)] max-[640px]:flex-col max-[640px]:items-start">
            <div>
                <span
                    class="inline-flex items-center gap-2 px-3 py-1.5 pl-2.5 bg-white/70 border border-border-soft rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue"><span
                        class="srv3-eyebrow-dot w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>{{ $eyebrowText }} ·
                    {{ $eyebrowCount }}</span>
                <h2 id="srv3-heading"
                    class="flex flex-col font-display text-[clamp(36px,5vw,64px)] font-extrabold tracking-display leading-none text-brand-navy-ink mt-[18px]">
                    <span>{{ $headingLineOne }}</span><span
                        class="bg-grad-mark bg-clip-text text-transparent">{{ $headingHighlight }}</span>
                </h2>
            </div>
            <a href="{{ $ctaLink }}"
                class="srv3-cta-ghost inline-flex items-center gap-2 text-[13.5px] font-semibold text-brand-navy-ink no-underline px-4 py-2.5 bg-white border border-border-default rounded-pill whitespace-nowrap max-[640px]:self-start">{{ $ctaText }}
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
        </div>

        @if ($categoryItems->isNotEmpty())
            <ul class="relative z-1 list-none p-0 m-0 flex flex-col bg-white border border-border-default rounded-3xl overflow-hidden shadow-srv-stack"
                role="list" data-srv-stack>
                @foreach ($categoryItems as $index => $category)
                    @php
                        $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                        $colorClass = $cardColors[$index % count($cardColors)];
                        $featuredService = $category->services->first();
                        $features = $featuredService?->features ?? [];
                        $previewFeatures = array_slice($features, 0, 3);
                        $ctaLabel = $featuredService?->cta_label ?: 'Get started';
                        $ctaUrl = $featuredService?->cta_url ?: '#contact';
                    @endphp
                    <li class="srv3-card {{ $colorClass }} relative border-t border-border-soft {{ $loop->first ? 'first:border-t-0' : '' }}"
                        data-srv-card data-srv-id="{{ $category->slug }}" role="listitem">
                        <button type="button"
                            class="srv3-row grid grid-cols-[56px_44px_minmax(0,1.4fr)_minmax(0,1fr)_auto_32px] items-center gap-[18px] w-full py-[22px] px-[clamp(20px,2.4vw,32px)] bg-transparent border-none text-left cursor-pointer text-inherit max-[1100px]:grid-cols-[44px_40px_1fr_28px] max-[1100px]:gap-[14px] max-[1100px]:p-[18px] max-[640px]:grid-cols-[32px_1fr_28px] max-[640px]:gap-3 max-[640px]:p-4"
                            data-srv-toggle aria-expanded="false">
                            <span
                                class="srv3-row-num font-mono text-[11.5px] font-semibold tracking-[0.14em] text-text-light tabular-nums max-[1100px]:text-micro">{{ $num }}</span>
                            <span
                                class="srv3-row-icon w-11 h-11 rounded-md bg-white border border-border-default inline-grid place-items-center text-text-muted max-[640px]:hidden">
                                <i class="ph {{ $category->icon ?: 'ph-layers' }} text-lg"></i>
                            </span>
                            <span class="flex flex-col gap-1 min-w-0">
                                <a href="{{ route('home.service', $category->slug) }}"
                                    class="font-display text-[clamp(18px,1.6vw,22px)] font-bold tracking-heading leading-heading text-brand-navy-ink max-[640px]:text-body no-underline hover:text-brand-blue transition-colors"
                                    tabindex="-1">{{ $category->name }}</a>
                                @if ($category->description)
                                    <span
                                        class="text-[13px] text-text-muted leading-[1.4] max-[640px]:hidden">{{ $category->description }}</span>
                                @endif
                            </span>
                            @if (!empty($previewFeatures))
                                <span class="flex gap-1.5 flex-wrap justify-end max-[1100px]:hidden">
                                    @foreach ($previewFeatures as $feature)
                                        <span
                                            class="font-mono text-micro font-medium text-text-muted bg-white border border-border-default px-2 py-1 rounded-md">{{ $feature }}</span>
                                    @endforeach
                                </span>
                            @else
                                <span class="max-[1100px]:hidden"></span>
                            @endif
                            <span class="max-[1100px]:hidden"></span>
                            <span
                                class="srv3-row-toggle w-8 h-8 rounded-pill bg-white border border-border-default inline-grid place-items-center text-text-muted"><i
                                    data-lucide="plus" class="w-4 h-4"></i></span>
                        </button>
                        <div class="srv3-collapse grid grid-rows-[0fr]" role="region">
                            <div class="srv3-collapse-inner overflow-hidden min-h-0 opacity-0 -translate-y-1">
                                <div
                                    class="grid grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] gap-[clamp(28px,3.5vw,56px)] px-[clamp(20px,2.4vw,32px)] pb-[clamp(28px,3vw,36px)] items-start max-[1100px]:grid-cols-1 max-[1100px]:gap-6 max-[640px]:px-4 max-[640px]:pb-[22px]">
                                    <div class="flex flex-col gap-[22px]">
                                        @if ($featuredService?->description)
                                            <p class="text-[15.5px] leading-[1.7] text-text-muted m-0 max-w-[60ch]">
                                                {{ $featuredService->description }}
                                            </p>
                                        @elseif ($category->description)
                                            <p class="text-[15.5px] leading-[1.7] text-text-muted m-0 max-w-[60ch]">
                                                {{ $category->description }}
                                            </p>
                                        @endif
                                        @if (!empty($features))
                                            <div
                                                class="flex items-center gap-3.5 py-3.5 border-t border-b border-border-soft flex-wrap">
                                                <span
                                                    class="font-mono text-[10.5px] font-semibold tracking-[0.14em] uppercase text-text-light">Built
                                                    on</span>
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach ($features as $feature)
                                                        <span
                                                            class="font-mono text-micro font-medium text-text-muted bg-white border border-border-default px-2 py-1 rounded-md">{{ $feature }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        <div
                                            class="flex items-center gap-[18px] flex-wrap mt-1 max-[640px]:flex-col max-[640px]:items-stretch">
                                            <a href="{{ route('home.service', $category->slug) }}"
                                                class="srv3-cta-primary inline-flex items-center w-fit gap-2 text-sm font-semibold text-white bg-brand-navy-ink px-[18px] py-3 rounded-md no-underline">{{ $ctaLabel }}
                                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
                                            <a href="#case-studies"
                                                class="srv3-cta-secondary relative text-[13.5px] w-fit font-semibold text-text-muted no-underline pb-0.5">See
                                                case studies</a>
                                        </div>
                                    </div>
                                    <div
                                        class="relative bg-white border border-border-soft rounded-lg overflow-hidden min-h-[220px]">
                                        <img src="{{ $featuredService?->media?->url ?? 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=900&q=80' }}"
                                            alt="{{ $category->name }}" loading="lazy"
                                            class="absolute inset-0 w-full h-full object-cover" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
