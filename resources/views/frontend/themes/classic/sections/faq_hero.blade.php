@php
    $d = $section->data ?? [];
    $eyebrow = $d['eyebrow'] ?? __('FAQ');
    $headingLine = $d['heading_line'] ?? __('Questions teams ask');
    $headingHighlight = $d['heading_highlight'] ?? __('before they hire us.');
    $description = $d['description'] ?? __('Clear answers about process, pricing, collaboration, ownership, and support.');
@endphp
<section class="relative isolate overflow-hidden bg-bg-soft border-b border-border-default pt-32 pb-0 -mt-28"
    aria-labelledby="faq-hero-heading">
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
        <span class="absolute bg-tint-blue top-0 left-1/2 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-green/60 top-[160px] left-0 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-blue/70 top-[320px] right-0 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-green/40 top-[480px] left-1/4 w-1/4 h-[160px]"></span>
    </div>

    <div class="section-container relative z-10 pb-[clamp(48px,7vw,96px)] pt-14">
        <!-- Eyebrow -->
        <span
            class="inline-flex items-center gap-2 px-3 py-1.5 pl-2.5 bg-white/70 border border-border-soft rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue mb-6 shadow-hero-eyebrow backdrop-blur-md">
            <span class="srv3-eyebrow-dot w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>{{ $eyebrow }}
        </span>

        <!-- Heading -->
        <h1 id="faq-hero-heading"
            class="font-display text-[clamp(36px,4.5vw,62px)] font-extrabold leading-tight-display tracking-display text-brand-navy-ink text-balance max-w-[22ch]">
            {{ $headingLine }}<br />
            <span class="bg-grad-mark bg-clip-text text-transparent">{{ $headingHighlight }}</span>
        </h1>

        <p class="mt-5 font-body text-body leading-relaxed-body text-text-muted max-w-[50ch]">
            {{ $description }}
        </p>
    </div>
</section>
