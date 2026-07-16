@php
    $d = $section->data ?? [];
    $eyebrowText     = $d['eyebrow_text']      ?? __('Technology stack');
    $headingLineOne  = $d['heading_line_one']   ?? __('The tools we ship with —');
    $headingHighlight = $d['heading_highlight'] ?? __('every one, opinionated.');
    $subheading      = $d['subheading']         ?? __('Every technology earns its place. No bloat, no trend-chasing — just the stack that ships fastest and scales longest.');
@endphp

    <section class="relative py-18 md:py-24 lg:py-[120px] bg-bg-section overflow-hidden isolate"
        aria-labelledby="stk2-heading">
        <!-- Dashed grid -->
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
        </div>
        <div class="section-container relative z-1">
            <div class="flex flex-col items-center gap-10 md:gap-14 lg:gap-16">
                <!-- Heading -->
                <div class="max-w-[720px] text-center">
                    <span
                        class="inline-flex items-center gap-2 py-1.5 pl-2.5 pr-3 bg-white/70 border border-tint-navy rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue"><span
                            class="w-1.5 h-1.5 rounded-pill bg-brand-blue"
                            style="box-shadow: 0 0 0 3px rgba(33, 72, 255, 0.2)"></span>{{ $eyebrowText }}</span>
                    <h2 id="stk2-heading"
                        class="flex flex-col font-display text-[clamp(32px,4.6vw,56px)] font-extrabold tracking-heading leading-[1.06] text-brand-navy-ink mt-[18px] text-balance">
                        <span>{{ $headingLineOne }}</span><span
                            class="bg-grad-mark bg-clip-text text-transparent">{{ $headingHighlight }}</span>
                    </h2>
                    <p class="mt-3.5 text-[15px] leading-[1.65] text-text-muted max-w-[52ch] mx-auto">
                        {{ $subheading }}
                    </p>
                </div>

                @if($techStackCategories->isNotEmpty())
                    <!-- Category toggle -->
                    <div class="stk2-toggle relative inline-flex items-stretch p-1.5 bg-white border border-border-soft rounded-pill shadow-sm"
                        role="tablist" aria-label="{{ __('Stack categories') }}" data-stk-toggle>
                        <span class="stk2-toggle-pill absolute top-1.5 bottom-1.5 overflow-hidden rounded-pill"
                            data-stk-pill style="--idx: 0; --total: {{ $techStackCategories->count() }}"></span>
                        @foreach($techStackCategories as $stackCategory)
                            <button type="button" role="tab"
                                class="stk2-toggle-btn relative z-1 flex-1 py-2.5 px-5 md:px-7 bg-transparent border-none font-sans text-body-sm font-semibold tracking-body text-text-muted cursor-pointer whitespace-nowrap"
                                data-stk-cat="{{ $stackCategory->slug }}">
                                {{ $stackCategory->name }}
                            </button>
                        @endforeach
                    </div>

                    <!-- Panels -->
                    @foreach($techStackCategories as $stackCategoryIndex => $stackCategory)
                        <div class="grid grid-cols-4 md:grid-cols-5 lg:grid-cols-8 gap-3 md:gap-4 w-full" role="tabpanel"
                            @if($stackCategoryIndex > 0) style="display: none" @endif
                            data-stk-panel="{{ $stackCategory->slug }}">
                            @foreach($stackCategory->stacks as $stackIndex => $stack)
                                <article
                                    class="stk2-tool-card flex flex-col items-center gap-3 p-4 md:p-5 bg-white border border-border-soft rounded-2xl shadow-xs hover:shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-default"
                                    style="--card-delay: {{ $stackIndex * 60 }}ms">
                                    @if($stack->media?->url)
                                        <span class="w-12 h-12 rounded-xl inline-grid place-items-center {{ $stack->logo_bg_color ? '' : 'bg-white border border-border-soft' }}"
                                            @if($stack->logo_bg_color) style="background-color: {{ $stack->logo_bg_color }}" @endif>
                                            <img src="{{ $stack->media->url }}" alt="{{ e($stack->name) }}"
                                                class="w-6 h-6{{ $stack->logo_invert ? ' brightness-0 invert' : '' }}" />
                                        </span>
                                    @else
                                        <span class="w-12 h-12 rounded-xl inline-grid place-items-center bg-[#e5e7eb] font-display text-[11px] font-extrabold text-neutral-600 text-center leading-tight px-1">
                                            {{ mb_strimwidth($stack->name, 0, 4) }}
                                        </span>
                                    @endif
                                    <span
                                        class="font-display text-[13px] font-semibold tracking-body text-text-strong text-center">{{ $stack->name }}</span>
                                </article>
                            @endforeach
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </section>
