@php
    $d = $section->data ?? [];
    $eyebrow = $d['eyebrow'] ?? __('Who we are');
    $heading = $d['heading'] ?? __('A studio obsessed with craft and clarity');
    $body1 = $d['body_paragraph_one'] ?? __('Classic started as a two-person consultancy with one conviction: most software fails because teams rush to code before they understand the problem. We took the opposite bet — start slow, think hard, then build fast.');
    $body2 = $d['body_paragraph_two'] ?? __("Today we're a 28-person studio spanning product design, full-stack engineering, and DevOps. We've shipped 60+ products across fintech, health-tech, logistics, and SaaS — always as embedded partners, never as body-shop contractors.");

    $tags = $d['tags'] ?? [];
    $metrics = $d['metrics'] ?? [];
@endphp

<section id="our-story" class="relative isolate overflow-hidden bg-bg-section py-[clamp(72px,9vw,120px)]" aria-labelledby="story-heading">
    <!-- Dashed grid lines -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_80%,transparent_100%)]" aria-hidden="true">
        <div class="absolute top-0 bottom-0 left-1/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.07)]"></div>
        <div class="absolute top-0 bottom-0 left-1/2 w-0 border-l border-dashed border-[rgba(17,18,74,0.07)]"></div>
        <div class="absolute top-0 bottom-0 left-3/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.07)]"></div>
    </div>

    <div class="section-container relative z-10">
        <div class="grid gap-[clamp(40px,6vw,80px)] lg:grid-cols-2 items-center">
            <!-- Copy -->
            <div>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 pl-2.5 bg-white/70 border border-border-soft rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue mb-5">
                    <span class="srv3-eyebrow-dot w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>{{ $eyebrow }}
                </span>
                <h2 id="story-heading" class="font-display text-[clamp(32px,4vw,52px)] font-extrabold leading-none tracking-display text-brand-navy-ink text-balance mb-6">
                    {{ $heading }}
                </h2>
                <p class="font-body text-body leading-relaxed-body text-text-muted mb-4">
                    {{ $body1 }}
                </p>
                <p class="font-body text-body leading-relaxed-body text-text-muted mb-8">
                    {{ $body2 }}
                </p>
                <div class="flex flex-wrap gap-2.5">
                    @foreach ($tags as $tag)
                        @php $text = $tag['text'] ?? ''; @endphp
                        @if ($text)
                            @php
                                $tagClasses = 'bg-white border-border-default text-text-muted';
                                if ($loop->index == 0) {
                                    $tagClasses = 'bg-tint-blue border-tint-blue-strong text-brand-blue';
                                } elseif ($loop->index == 1) {
                                    $tagClasses = 'bg-tint-green border-brand-green/20 text-brand-green';
                                }
                            @endphp
                            <span class="inline-flex items-center gap-1.5 rounded-pill border px-3.5 py-1.5 font-mono text-micro font-semibold tracking-[0.15em] uppercase {{ $tagClasses }}">
                                <i data-lucide="check" class="w-3 h-3"></i> {{ $text }}
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Metric cards grid -->
            <div class="grid grid-cols-2 gap-4">
                @foreach ($metrics as $metric)
                    @php
                        $icon = $metric['icon'] ?? 'calendar';
                        $value = $metric['value'] ?? '';
                        $label = $metric['label'] ?? '';
                        $color = $metric['color'] ?? 'blue';

                        // Resolve background styles and classes
                        $cardClasses = 'bg-white border-border-soft p-6 shadow-sm aboutv2-metric-card';
                        $iconWrapper = '';
                        $valueClasses = 'font-display text-[clamp(32px,3.5vw,44px)] font-extrabold leading-none tracking-display text-brand-blue';
                        $labelClasses = 'font-mono text-micro text-text-muted leading-body tracking-[0.08em] uppercase';

                        if ($loop->first) {
                            $cardClasses = 'col-span-2 flex items-center gap-5 bg-white border-border-soft p-6 shadow-sm aboutv2-metric-card';
                            $iconWrapper = '<div class="flex-none w-12 h-12 rounded-xl bg-tint-blue flex items-center justify-center text-brand-blue"><i class="ph '.$icon.' text-xl"></i></div>';
                            $valueClasses = 'font-display text-[clamp(28px,3vw,36px)] font-extrabold leading-none tracking-display text-brand-navy-ink';
                            $labelClasses = 'font-mono text-micro text-text-muted mt-1 tracking-[0.08em] uppercase';
                        } elseif ($color === 'navy') {
                            $cardClasses = 'flex flex-col gap-2 rounded-3xl bg-brand-navy-ink border border-white/8 p-6 shadow-sm aboutv2-metric-card';
                            $valueClasses = 'font-display text-[clamp(32px,3.5vw,44px)] font-extrabold leading-none tracking-display text-white';
                            $labelClasses = 'font-mono text-micro text-white/45 leading-body tracking-[0.08em] uppercase';
                        } elseif ($color === 'brand') {
                            $cardClasses = 'flex flex-col gap-2 rounded-3xl border border-brand-blue/20 p-6 shadow-brand aboutv2-metric-card';
                            $valueClasses = 'font-display text-[clamp(32px,3.5vw,44px)] font-extrabold leading-none tracking-display text-white';
                            $labelClasses = 'font-mono text-micro text-white/60 leading-body tracking-[0.08em] uppercase';
                        }
                    @endphp
                    
                    @if ($loop->first)
                        <div class="{{ $cardClasses }}">
                            {!! $iconWrapper !!}
                            <div>
                                <p class="{{ $valueClasses }}">{{ $value }}</p>
                                <p class="{{ $labelClasses }}">{{ $label }}</p>
                            </div>
                        </div>
                    @else
                        <div class="{{ $cardClasses }}" @if($color === 'brand') style="background: var(--background-image-grad-brand)" @endif>
                            <p class="{{ $valueClasses }}">
                                {!! str_replace(['+', '%', '/5'], ['<span class="text-brand-green">+</span>', '<span class="text-brand-green">%</span>', '<span class="text-accent-lime">/5</span>'], e($value)) !!}
                            </p>
                            <p class="{{ $labelClasses }}">{{ $label }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</section>
