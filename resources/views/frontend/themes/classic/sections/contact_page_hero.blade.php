@php
    $d = $section->data ?? [];
    $eyebrow = $d['eyebrow'] ?? __('Get in touch');
    $headingLine = $d['heading_line'] ?? __("Let's build something");
    $headingHighlight = $d['heading_highlight'] ?? __('great together.');
    $description =
        $d['description'] ??
        'Whether you have a fully scoped project or just an early idea — reach out. We respond within 48 hours and our first call is always free.';

    $trustSignals = $d['trust_signals'] ?? [
        ['icon' => 'clock',        'color' => 'green', 'label' => 'Reply in 48 hrs',  'detail' => '— guaranteed'],
        ['icon' => 'calendar',     'color' => 'blue',  'label' => 'Free scope call',  'detail' => '— 30 minutes, no pitch'],
        ['icon' => 'shield-check', 'color' => 'blue',  'label' => 'NDA available',    'detail' => '— on request'],
    ];
@endphp
<section id="contact"
    class="relative isolate overflow-hidden bg-bg-soft border-b border-border-default pt-32 pb-0 -mt-28"
    aria-labelledby="contact-hero-heading">
    <!-- Dashed grid lines -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_78%,transparent_100%)]"
        aria-hidden="true">
        <div class="absolute top-0 bottom-0 left-1/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-1/2 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-3/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[160px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[320px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[480px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
    </div>
    <!-- Tinted block accents -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_78%,transparent_100%)]"
        aria-hidden="true">
        <span class="absolute bg-tint-blue top-0 left-1/4 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-green/60 top-[160px] right-0 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-blue/70 top-[320px] left-0 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-green/40 top-[480px] left-1/2 w-1/4 h-[160px]"></span>
    </div>

    <div class="section-container relative z-10 pb-[clamp(48px,7vw,96px)] pt-14">
        <!-- Eyebrow -->
        <span
            class="inline-flex items-center gap-2 px-3 py-1.5 pl-2.5 bg-white/70 border border-border-soft rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue mb-6 shadow-hero-eyebrow backdrop-blur-md">
            <span class="srv3-eyebrow-dot w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>{{ $eyebrow }}
        </span>

        <!-- Heading -->
        <h1 id="contact-hero-heading"
            class="font-display text-[clamp(36px,4.5vw,62px)] font-extrabold leading-tight-display tracking-display text-brand-navy-ink text-balance max-w-[18ch]">
            {{ $headingLine }}<br />
            <span class="bg-grad-mark bg-clip-text text-transparent">{{ $headingHighlight }}</span>
        </h1>

        <p class="mt-5 font-body text-body leading-relaxed-body text-text-muted max-w-[50ch]">
            {{ $description }}
        </p>

        <!-- Trust signals row -->
        <div class="mt-8 flex flex-wrap items-center gap-x-8 gap-y-3">
            @foreach ($trustSignals as $signal)
                @php
                    $isGreen = ($signal['color'] ?? 'blue') === 'green';
                    $iconBg   = $isGreen ? 'bg-tint-green text-brand-green' : 'bg-tint-blue text-brand-blue';
                @endphp
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg {{ $iconBg }} inline-grid place-items-center flex-none">
                        <i class="ph {{ $signal['icon'] ?? 'ph-check' }} text-base"></i>
                    </span>
                    <span class="font-body text-body-sm text-text-muted"><span class="font-semibold text-text-strong">{{ $signal['label'] ?? '' }}</span> {{ $signal['detail'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    </div>
</section>
