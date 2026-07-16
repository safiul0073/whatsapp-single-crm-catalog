@php $d = $section->data ?? []; @endphp
<section id="heroEd" class="hero-ed bg-neutral-0 py-4 sm:py-6">
    <div class="container grid gap-4 lg:min-h-[560px] lg:grid-cols-[2.5fr_3.2fr]">
        <div class="grid min-h-[26rem] grid-rows-[auto_1fr_auto] rounded-2xl bg-deep p-7 text-neutral-0 sm:p-9 lg:p-12">
            <h1 id="heroTitle" class="hero-ed__title font-title text-[2.75rem] leading-[0.92] font-extrabold tracking-[-0.03em] sm:text-6xl lg:text-[4.4rem] xl:text-[5.4rem]">
                @if (!empty($d['heading_line_1']))
                    <span class="hero-ed__line"><span>{{ $d['heading_line_1'] }}</span></span>
                @endif
                @if (!empty($d['heading_line_2']))
                    <span class="hero-ed__line"><span>{{ $d['heading_line_2'] }}</span></span>
                @endif
                @if (!empty($d['heading_accent']))
                    <span class="hero-ed__line"><span class="text-accent">{{ $d['heading_accent'] }}</span></span>
                @endif
            </h1>

            <div></div>

            <div>
                @if (!empty($d['subheading']))
                    <p data-hero-fade class="text-xl font-medium text-neutral-0/80 sm:text-2xl">
                        {{ $d['subheading'] }}
                    </p>
                @endif
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    @if (!empty($d['cta_primary_text']))
                        <a href="{{ $d['cta_primary_url'] ?? route('login') }}" data-hero-fade class="btn btn-primary min-h-12">{{ $d['cta_primary_text'] }}</a>
                    @endif
                    @if (!empty($d['cta_secondary_text']))
                        <a href="{{ $d['cta_secondary_url'] ?? route('features') }}" data-hero-fade class="hero-ed__disc min-h-12">
                            <span class="hero-ed__play">
                                <svg viewBox="0 0 24 24" class="h-3 w-3" aria-hidden="true"><path d="M8 5v14l11-7z" fill="currentColor" /></svg>
                            </span>
                            {{ $d['cta_secondary_text'] }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid h-80 grid-cols-[1.45fr_0.72fr] grid-rows-1 gap-4 sm:h-[28rem] lg:h-auto">
            @php
                $images = collect($d['images'] ?? [])->map(function ($image) {
                    if (is_array($image)) {
                        return [
                            'url' => $image['url'] ?? $image['src'] ?? '',
                            'alt' => $image['alt'] ?? '',
                        ];
                    }

                    return ['url' => $image, 'alt' => ''];
                })->values();
            @endphp
            @if (!empty($images[0]))
                <div class="hero-ed__frame" data-hero-frame>
                    <img src="{{ $images[0]['url'] }}" alt="{{ $images[0]['alt'] }}" class="h-full w-full object-cover" />
                </div>
            @else
                <div class="hero-ed__frame" data-hero-frame>
                    <img src="{{ asset('assets/wapro/images/hero-tedy-1.webp') }}" alt="A team collaborating on customer messaging" class="h-full w-full object-cover" />
                </div>
            @endif
            <div class="grid grid-rows-[0.9fr_1.6fr] gap-4">
                <div class="hero-ed__frame" data-hero-frame>
                    @if (!empty($images[1]))
                        <img src="{{ $images[1]['url'] }}" alt="{{ $images[1]['alt'] }}" class="h-full w-full object-cover" />
                    @else
                        <img src="{{ asset('assets/wapro/images/hero-tedy-2.webp') }}" alt="Two colleagues reviewing campaign results" class="h-full w-full object-cover" />
                    @endif
                </div>
                <div class="hero-ed__frame" data-hero-frame>
                    @if (!empty($images[2]))
                        <img src="{{ $images[2]['url'] }}" alt="{{ $images[2]['alt'] }}" class="h-full w-full object-cover" />
                    @else
                        <img src="{{ asset('assets/wapro/images/hero-tedy-3.webp') }}" alt="A happy customer support team" class="h-full w-full object-cover" />
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
