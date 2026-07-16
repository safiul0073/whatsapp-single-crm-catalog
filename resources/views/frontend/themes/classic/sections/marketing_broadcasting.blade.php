@php $d = $section->data ?? []; @endphp
<section class="spy-section">
    <div class="container grid items-start gap-12 lg:grid-cols-2 lg:gap-16">
        <div class="spot-sticky" data-reveal>
            @if (!empty($d['eyebrow']))
                <span class="eyebrow">{{ $d['eyebrow'] }}</span>
            @endif
            @if (!empty($d['heading']))
                <h2 class="heading-1 mt-4">{{ $d['heading'] }}</h2>
            @endif
            @if (!empty($d['subheading']))
                <p class="lead-text mt-4">{{ $d['subheading'] }}</p>
            @endif
            @php $bullets = $d['bullets'] ?? []; @endphp
            @if (!empty($bullets))
                <ul class="mt-7 space-y-3">
                    @foreach ($bullets as $bullet)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary text-neutral-0"><svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                            <span class="l-text text-title">{{ $bullet }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if (!empty($d['cta_text']))
                <a href="{{ $d['cta_url'] ?? route('login') }}" class="btn btn-primary mt-8">{{ $d['cta_text'] }}</a>
            @endif
        </div>

        <div class="flex flex-col gap-6">
            @php $visualCards = $d['visual_cards'] ?? []; @endphp
            @if (!empty($visualCards))
                @foreach ($visualCards as $index => $visualCard)
                    @php $vDelay = $index * 0.12; @endphp
                    <div data-reveal style="transition-delay: {{ $vDelay }}s" class="rounded-3xl border border-neutral-200 {{ $index % 2 === 0 ? 'bg-neutral-0' : 'bg-section' }} p-6 shadow-[0_30px_70px_-40px_rgba(10,27,20,0.35)]">
                        @if (($visualCard['type'] ?? 'stats') === 'stats')
                            @if (!empty($visualCard['heading']) || !empty($visualCard['value']))
                                <div class="f-between">
                                    <div>
                                        @if (!empty($visualCard['heading']))
                                            <p class="s-text text-neutral-500">{{ $visualCard['heading'] }}</p>
                                        @endif
                                        @if (!empty($visualCard['value']))
                                            <p class="font-title text-3xl font-extrabold text-title">{{ $visualCard['value'] }}</p>
                                        @endif
                                    </div>
                                    @if (!empty($visualCard['badge']))
                                        <span class="badge badge-soft">{{ $visualCard['badge'] }}</span>
                                    @endif
                                </div>
                            @endif
                            @if (!empty($visualCard['chart_bars']))
                                <div class="mt-6 flex h-40 items-end gap-3">
                                    @foreach ($visualCard['chart_bars'] as $bar)
                                        <div class="flex-1 rounded-t-lg {{ !empty($bar['accent']) ? 'bg-primary' : 'bg-neutral-100' }}" style="height:{{ $bar['height'] ?? '50%' }}"></div>
                                    @endforeach
                                </div>
                            @endif
                            @if (!empty($visualCard['stats']))
                                <div class="mt-4 grid grid-cols-3 gap-3 border-t border-neutral-100 pt-4 text-center">
                                    @foreach ($visualCard['stats'] as $stat)
                                        <div><p class="font-title text-lg font-bold text-title">{{ $stat['value'] ?? '' }}</p><p class="s-text">{{ $stat['label'] ?? '' }}</p></div>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            @if (!empty($visualCard['heading']))
                                <p class="s-text mb-4 font-semibold text-title">{{ $visualCard['heading'] }}</p>
                            @endif
                            @if (!empty($visualCard['rows']))
                                <div class="space-y-2.5">
                                    @foreach ($visualCard['rows'] as $row)
                                        <div class="f-between rounded-xl {{ $index % 2 === 0 ? 'bg-neutral-0' : 'bg-section' }} px-3 py-2.5">
                                            <span class="text-sm font-medium text-title">{{ $row['label'] ?? '' }}</span>
                                            @php $statusType = $row['status_type'] ?? 'soft'; @endphp
                                            @if ($statusType === 'soft')
                                                <span class="badge badge-soft">{{ $row['status'] ?? '' }}</span>
                                            @elseif ($statusType === 'warning')
                                                <span class="rounded-full bg-warning/15 px-2.5 py-0.5 text-xs font-semibold text-warning">{{ $row['status'] ?? '' }}</span>
                                            @elseif ($statusType === 'info')
                                                <span class="rounded-full bg-info/15 px-2.5 py-0.5 text-xs font-semibold text-info">{{ $row['status'] ?? '' }}</span>
                                            @else
                                                <span class="text-xs font-semibold text-body">{{ $row['status'] ?? '' }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</section>
