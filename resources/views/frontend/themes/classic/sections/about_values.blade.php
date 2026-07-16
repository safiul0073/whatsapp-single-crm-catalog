@php
    $d = $section->data ?? [];
    $eyebrow = $d['eyebrow'] ?? __('What drives us');
    $headingLineOne = $d['heading_line_one'] ?? __('Values that shape');
    $headingHighlight = $d['heading_highlight'] ?? __('every decision.');
    $subheading = $d['subheading'] ?? __("We don't hang our values on a wall — we bake them into how we scope, build, and ship.");

    $items = $d['items'] ?? [];
@endphp

<section class="relative isolate overflow-hidden bg-white py-[clamp(72px,9vw,120px)]" aria-labelledby="values-heading">
    <!-- Dashed grid lines -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_80%,transparent_100%)]" aria-hidden="true">
        <div class="absolute top-0 bottom-0 left-1/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.07)]"></div>
        <div class="absolute top-0 bottom-0 left-1/2 w-0 border-l border-dashed border-[rgba(17,18,74,0.07)]"></div>
        <div class="absolute top-0 bottom-0 left-3/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.07)]"></div>
    </div>

    <div class="section-container relative z-10">
        <!-- Section header -->
        <div class="flex items-end justify-between gap-8 mb-[clamp(36px,4vw,56px)] max-[640px]:flex-col max-[640px]:items-start">
            <div>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 pl-2.5 bg-white/70 border border-border-soft rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue">
                    <span class="srv3-eyebrow-dot w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>{{ $eyebrow }}
                </span>
                <h2 id="values-heading" class="font-display text-[clamp(32px,4vw,56px)] font-extrabold leading-none tracking-display text-brand-navy-ink mt-[18px] text-balance">
                    {{ $headingLineOne }}<br /><span class="bg-grad-mark bg-clip-text text-transparent">{{ $headingHighlight }}</span>
                </h2>
            </div>
            <p class="max-w-[38ch] font-body text-body leading-relaxed-body text-text-muted max-[640px]:max-w-full">
                {{ $subheading }}
            </p>
        </div>

        <!-- Value cards — 3 col -->
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $item)
                @php
                    $icon = $item['icon'] ?? 'eye';
                    $color = $item['color'] ?? 'blue';
                    $title = $item['title'] ?? '';
                    $desc = $item['description'] ?? '';

                    // Resolve background style and color classes based on card color setting
                    if ($color === 'green') {
                        $bgStyle = 'background: linear-gradient(135deg, rgba(22, 199, 132, 0.05) 0%, rgba(22, 199, 132, 0.01) 60%, #fff 100%)';
                        $iconClasses = 'bg-tint-green border-brand-green/20 text-brand-green';
                    } elseif ($color === 'navy') {
                        $bgStyle = 'background: linear-gradient(135deg, rgba(15, 15, 73, 0.04) 0%, rgba(15, 15, 73, 0.01) 60%, #fff 100%)';
                        $iconClasses = 'bg-tint-navy border-border-strong/60 text-brand-navy-ink';
                    } else { // default blue
                        $bgStyle = 'background: linear-gradient(135deg, rgba(33, 72, 255, 0.04) 0%, rgba(33, 72, 255, 0.01) 60%, #fff 100%)';
                        $iconClasses = 'bg-tint-blue border-tint-blue-strong text-brand-blue';
                    }
                @endphp
                <div class="group flex flex-col gap-5 rounded-3xl border border-border-soft p-7 shadow-xs about-value-card" style="{{ $bgStyle }}">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center border about-value-icon {{ $iconClasses }}">
                        <i class="ph {{ $icon }} text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-display text-h4 font-bold leading-heading tracking-heading text-brand-navy-ink mb-2">{{ $title }}</h3>
                        <p class="font-body text-body-sm leading-relaxed-body text-text-muted">{{ $desc }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
