@php $d = $section->data ?? []; @endphp
<section class="spy-section">
    <div class="container">
        <div
            class="cta-parallax flex min-h-72 items-center rounded-3xl px-6 py-12 text-neutral-0 sm:px-10 lg:px-14"
            data-parallax
            data-parallax-speed="0.3"
        >
            <div
                class="cta-parallax__bg"
                data-parallax-bg
                style="background-image: url('{{ !empty($d['background_image']) ? $d['background_image'] : asset('assets/wapro/images/hero-tedy-1.webp') }}')"
                aria-hidden="true"
            ></div>

            <div class="relative z-10 max-w-xl">
                @if (!empty($d['eyebrow']))
                    <span class="eyebrow text-accent">
                        <span class="inline-block h-px w-8 bg-current"></span>
                        {{ $d['eyebrow'] }}
                    </span>
                @endif
                @if (!empty($d['heading']))
                    <h2 class="mt-5 font-title text-3xl font-bold tracking-tight text-neutral-0 sm:text-4xl lg:text-[44px]">
                        {{ $d['heading'] }}
                    </h2>
                @endif
                @if (!empty($d['subheading']))
                    <p class="lead-text mt-4 max-w-md text-neutral-100">
                        {{ $d['subheading'] }}
                    </p>
                @endif
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    @if (!empty($d['cta_primary_text']))
                        <a href="{{ $d['cta_primary_url'] ?? route('login') }}" class="group btn relative overflow-hidden bg-neutral-0 text-deep hover:bg-neutral-100">
                            {{ $d['cta_primary_text'] }}
                            <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            <span class="shimmer"></span>
                        </a>
                    @endif
                    @if (!empty($d['cta_secondary_text']))
                        <a href="{{ $d['cta_secondary_url'] ?? route('pricing') }}" class="btn border border-neutral-0/40 text-neutral-0 hover:bg-neutral-0/10">{{ $d['cta_secondary_text'] }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
