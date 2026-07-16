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

        @php $steps = $d['steps'] ?? []; @endphp
        @if (!empty($steps))
            <div class="mt-14 grid gap-6 md:grid-cols-3">
                @foreach ($steps as $index => $step)
                    @php $delay = $index * 0.12; $num = str_pad($step['number'] ?? ($index + 1), 2, '0', STR_PAD_LEFT); @endphp
                    <div data-reveal style="transition-delay: {{ $delay }}s" class="group relative overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-0 p-7 transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-[0_24px_50px_-28px_rgba(31,170,83,0.5)]">
                        <span class="pointer-events-none absolute -top-4 -right-2 font-title text-8xl font-extrabold text-primary/5 transition-colors duration-300 group-hover:text-primary/10">{{ $num }}</span>
                        <span class="relative grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-neutral-0">
                            @if (!empty($step['icon_svg']))
                                {!! $step['icon_svg'] !!}
                            @else
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg>
                            @endif
                        </span>
                        <h3 class="heading-4 relative mt-5">{{ $step['title'] ?? '' }}</h3>
                        <p class="m-text relative mt-2">{{ $step['description'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
