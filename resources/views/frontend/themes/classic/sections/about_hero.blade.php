@php
    $d = $section->data ?? [];
    $eyebrow = $d['eyebrow'] ?? __('About Our Company');
    $headingLineOne = $d['heading_line_one'] ?? __('Your Trusted Digital Partner for Smarter');
    $headingHighlight = $d['heading_highlight'] ?? __('Software & Products');
    $description = $d['description'] ?? __('At Classic, we bring innovation, logic, and design together to build powerful digital solutions. As a product studio, we focus on creating results-driven software that matches real-world needs — from early planning all the way to launch.');
    $primaryCtaText = $d['primary_cta_text'] ?? __('Start Your Project');
    $primaryCtaLink = $d['primary_cta_link'] ?? '#contact';
    $secondaryCtaText = $d['secondary_cta_text'] ?? __('Our story');
    $secondaryCtaLink = $d['secondary_cta_link'] ?? '#our-story';

    $heroImage = media_url($d['hero_image_media_id'] ?? null) ?? 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?auto=format&fit=crop&w=900&q=80';
    $metricValue = $d['metric_value'] ?? '2.6x';
    $metricTitle = $d['metric_title'] ?? __('Faster time-to-market');
    $metricDescription = $d['metric_description'] ?? __('Streamlined process, faster delivery, no quality trade-offs.');

    $features = $d['features'] ?? [
        ['title' => 'Scalable Tech Stack'],
        ['title' => 'Responsive UI/UX Design'],
        ['title' => 'Quick Deployment'],
        ['title' => 'Continuous Support'],
        ['title' => 'Secure Architecture'],
        ['title' => 'Smart Marketing Approach'],
    ];
@endphp

<section class="relative isolate overflow-hidden bg-bg-soft border-b border-border-default pt-32 pb-0 -mt-28" aria-labelledby="about-hero-heading">
    <!-- Dashed grid lines -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_78%,transparent_100%)]" aria-hidden="true">
        <div class="absolute top-0 bottom-0 left-1/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-1/2 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-3/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[160px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[320px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[480px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[640px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
    </div>

    <!-- Tinted block accents snapped to grid -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_78%,transparent_100%)]" aria-hidden="true">
        <span class="absolute bg-tint-blue top-0 left-1/4 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-green/60 top-[160px] right-0 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-blue/70 top-[320px] left-0 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-blue top-[480px] left-1/2 w-1/4 h-[160px]"></span>
    </div>

    <div class="section-container relative z-10 grid items-center gap-12 pt-14 lg:grid-cols-2 lg:gap-16 xl:grid-cols-5 xl:gap-24">
        <!-- LEFT — Copy -->
        <div class="xl:col-span-3 pb-[clamp(48px,7vw,96px)]">
            <!-- Eyebrow -->
            <span class="inline-flex items-center gap-2 px-3 py-1.5 pl-2.5 bg-white/70 border border-border-soft rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue mb-6 shadow-hero-eyebrow backdrop-blur-md">
                <span class="srv3-eyebrow-dot w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>{{ $eyebrow }}
            </span>

            <!-- Heading -->
            <h1 id="about-hero-heading" class="font-display text-[clamp(36px,4.5vw,64px)] font-extrabold leading-tight-display tracking-display text-brand-navy-ink text-balance max-w-[16ch]">
                {{ $headingLineOne }}
                <span class="bg-grad-mark bg-clip-text text-transparent"> {{ $headingHighlight }}</span>
            </h1>

            <!-- Body copy -->
            <div class="mt-6 flex flex-col gap-4 font-body text-body leading-relaxed-body text-text-muted max-w-[56ch]">
                <p>{{ $description }}</p>
            </div>

            <!-- Feature checks — 2-col grid -->
            <div class="mt-8 grid grid-cols-2 gap-x-8 gap-y-3 max-w-[52ch]">
                @foreach ($features as $feat)
                    @php $title = $feat['title'] ?? ''; @endphp
                    @if ($title)
                        <div class="flex items-center gap-2.5">
                            <span class="flex-none w-5 h-5 rounded-pill bg-brand-blue flex items-center justify-center text-white">
                                <i data-lucide="check" class="w-3 h-3"></i>
                            </span>
                            <span class="font-body text-body-sm font-medium text-text-default">{{ $title }}</span>
                        </div>
                    @endif
                @endforeach
            </div>

            <!-- CTAs -->
            <div class="mt-10 flex items-center gap-4">
                <a href="{{ $primaryCtaLink }}" class="hero-cta-primary inline-flex items-center gap-2 rounded-md border border-white/15 bg-gradient-to-b from-brand-blue to-primary-hover px-5 py-2.75 text-sm font-bold text-white no-underline shadow-hero-cta">
                    {{ $primaryCtaText }} <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
                <a href="{{ $secondaryCtaLink }}" class="inline-flex items-center gap-2 font-body text-body-sm font-semibold text-text-muted no-underline about-hero-cta-ghost">
                    {{ $secondaryCtaText }} <i data-lucide="arrow-down" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </div>

        <!-- RIGHT — Photo + Metric card -->
        <div class="xl:col-span-2 relative self-stretch h-fit max-lg:hidden">
            <div class="inset-x-0 top-0 bottom-0">
                <img src="{{ $heroImage }}" alt="Classic team collaborating on a product" class="w-full h-[448px] object-cover object-center rounded-3xl" loading="eager" />
                <!-- Dark fade at bottom for card legibility -->
                <div class="absolute inset-x-0 bottom-0 h-2/3 rounded-b-3xl" style="background: linear-gradient(to top, rgba(15,15,73,0.85) 0%, rgba(15,15,73,0.4) 50%, transparent 100%)"></div>
                <!-- Metric card — absolute over photo -->
                <div class="absolute -left-[150px] -bottom-10 rounded-xl bg-gradient-to-b from-brand-blue/90 to-primary-hover/95 p-5 shadow-brand backdrop-blur-sm max-w-[400px]">
                    <div class="flex items-center gap-4">
                        <div class="flex-none">
                            <p class="font-display text-[clamp(36px,3.5vw,52px)] font-extrabold leading-none tracking-display text-white">{!! str_replace('x', '<span class="text-accent-lime text-[0.55em]">x</span>', e($metricValue)) !!}</p>
                        </div>
                        <div class="w-px h-12 bg-white/20 flex-none"></div>
                        <div>
                            <p class="font-mono text-micro font-bold tracking-[0.14em] uppercase text-white/60 mb-1">{{ $metricTitle }}</p>
                            <p class="font-body text-body-sm leading-body text-white/70">{{ $metricDescription }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
