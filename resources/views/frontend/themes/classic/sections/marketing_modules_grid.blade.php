@php $d = $section->data ?? []; @endphp
<section class="spy-section">
    <div class="container">
        <div class="mx-auto max-w-2xl text-center">
            @if (!empty($d['eyebrow']))
                <span class="eyebrow">{{ $d['eyebrow'] }}</span>
            @endif
            @if (!empty($d['heading']))
                <h2 class="heading-1 mt-4">{{ $d['heading'] }}</h2>
            @endif
            @if (!empty($d['subheading']))
                <p class="lead-text mt-4">{{ $d['subheading'] }}</p>
            @endif
        </div>

        @php
            $cards = $d['cards'] ?? $d['modules'] ?? [];
        @endphp
        @if (!empty($cards))
            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cards as $index => $card)
                    @php
                        $delay = ($index % 3) * 0.06;
                        $num = str_pad($card['number'] ?? ($index + 1), 2, '0', STR_PAD_LEFT);
                        $title = $card['title'] ?? $card['label'] ?? '';
                    @endphp
                    <div data-reveal style="transition-delay: {{ $delay }}s" class="group relative overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-0 p-7 transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-[0_24px_50px_-28px_rgba(31,170,83,0.5)]">
                        <span class="pointer-events-none absolute -top-4 -right-2 font-title text-8xl font-extrabold text-primary/5 transition-colors duration-300 group-hover:text-primary/10">{{ $num }}</span>
                        <span class="relative grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-neutral-0">
                            @if (!empty($card['icon_svg']))
                                {!! $card['icon_svg'] !!}
                            @else
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 4.7a2 2 0 0 0 2 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"/></svg>
                            @endif
                        </span>
                        <h3 class="heading-4 relative mt-5">{{ $title }}</h3>
                        <p class="m-text relative mt-2">{{ $card['description'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
