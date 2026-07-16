@php
    $d = $section->data ?? [];
    $reasons = $d['reasons'] ?? [];
    $leftCards = array_slice($reasons, 0, (int) ceil(count($reasons) / 2));
    $rightCards = array_slice($reasons, (int) ceil(count($reasons) / 2));
@endphp
<section class="spy-section bg-section">
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

        <div class="mt-14 grid items-center gap-6 lg:grid-cols-[1fr_1.1fr_1fr] lg:gap-8">
            <div class="flex flex-col gap-6 lg:order-1">
                @foreach ($leftCards as $index => $card)
                    <article data-reveal style="transition-delay: {{ $index * 0.12 }}s" class="choose-card">
                        <span class="choose-card__num choose-card__num--right">{{ str_pad($card['number'] ?? ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                @if (!empty($card['icon_svg']))
                                    {!! $card['icon_svg'] !!}
                                @else
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg>
                                @endif
                            </span>
                            <h3 class="heading-4">{{ $card['title'] ?? $card['label'] ?? '' }}</h3>
                        </div>
                        @if (!empty($card['description']))
                            <p class="m-text mt-3">{{ $card['description'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>

            <div data-reveal class="lg:order-2">
                <div class="relative overflow-hidden rounded-3xl bg-deep p-6 text-neutral-0 shadow-[0_30px_70px_-40px_rgba(10,27,20,0.6)] sm:p-8">
                    <div class="pointer-events-none absolute -top-16 -right-12 h-48 w-48 rounded-full bg-accent/20 blur-3xl animate-blob"></div>
                    <p class="s-text text-neutral-0/60">{{ $d['center_label'] ?? 'This month' }}</p>
                    <p class="mt-1 font-title text-4xl font-extrabold">{{ $d['center_value'] ?? '104.7k' }}</p>
                    <p class="m-text text-neutral-0/70">{{ $d['center_subtitle'] ?? 'messages delivered' }}</p>
                    <div class="mt-6 flex h-28 items-end gap-2">
                        <span class="h-10 w-full rounded bg-neutral-0/15"></span>
                        <span class="h-16 w-full rounded bg-neutral-0/25"></span>
                        <span class="h-12 w-full rounded bg-neutral-0/15"></span>
                        <span class="h-24 w-full rounded bg-accent"></span>
                        <span class="h-20 w-full rounded bg-accent/60"></span>
                        <span class="h-28 w-full rounded bg-accent"></span>
                    </div>
                    @php $bottomStats = $d['center_bottom_stats'] ?? []; @endphp
                    @if (!empty($bottomStats))
                        <div class="mt-6 grid grid-cols-3 gap-3 border-t border-neutral-0/10 pt-5 text-center">
                            @foreach ($bottomStats as $bottomStat)
                                <div><p class="font-title text-xl font-bold">{{ $bottomStat['value'] ?? '' }}</p><p class="s-text text-neutral-0/60">{{ $bottomStat['label'] ?? '' }}</p></div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex flex-col gap-6 lg:order-3">
                @php $rightNumOffset = count($leftCards); @endphp
                @foreach ($rightCards as $index => $card)
                    <article data-reveal style="transition-delay: {{ ($index * 0.12) + 0.06 }}s" class="choose-card">
                        <span class="choose-card__num choose-card__num--left">{{ str_pad($card['number'] ?? ($rightNumOffset + $index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="flex flex-row-reverse items-center gap-3 text-right">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                @if (!empty($card['icon_svg']))
                                    {!! $card['icon_svg'] !!}
                                @else
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 3 5-6"/></svg>
                                @endif
                            </span>
                            <h3 class="heading-4">{{ $card['title'] ?? $card['label'] ?? '' }}</h3>
                        </div>
                        @if (!empty($card['description']))
                            <p class="m-text mt-3 text-right">{{ $card['description'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>

        @if (!empty($d['cta_text']))
            <div class="mt-12 text-center">
                <a href="{{ $d['cta_url'] ?? route('login') }}" class="btn btn-primary">{{ $d['cta_text'] }}</a>
            </div>
        @endif
    </div>
</section>
