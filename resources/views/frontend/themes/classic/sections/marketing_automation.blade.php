@php $d = $section->data ?? []; @endphp
<section class="spy-section bg-section">
    <div class="container grid items-start gap-12 lg:grid-cols-2 lg:gap-16">
        <div class="order-2 flex flex-col gap-6 lg:order-1">
            @php $visualCards = $d['visual_cards'] ?? []; @endphp
            @if (!empty($visualCards))
                @foreach ($visualCards as $index => $visualCard)
                    @php $vDelay = $index * 0.12; @endphp
                    <div data-reveal style="transition-delay: {{ $vDelay }}s" class="rounded-3xl border border-neutral-200 bg-neutral-0 p-6 shadow-[0_30px_70px_-40px_rgba(10,27,20,0.35)]">
                        @if (($visualCard['type'] ?? 'rule') === 'rule')
                            @if (!empty($visualCard['heading']))
                                <p class="s-text text-neutral-500">{{ $visualCard['heading'] }}</p>
                            @endif
                            @if (!empty($visualCard['rule_body']) || !empty($visualCard['reply_preview']))
                                <div class="mt-4 rounded-xl bg-section p-4">
                                    @if (!empty($visualCard['rule_body']))
                                        <p class="s-text text-neutral-500">{{ __('When message contains') }}</p>
                                        <p class="m-text mt-1 font-semibold text-title">{{ $visualCard['rule_body'] }}</p>
                                    @endif
                                    @if (!empty($visualCard['reply_preview']))
                                        <div class="mt-4 max-w-[85%] rounded-lg rounded-bl-sm bg-[#dcf8c6] px-3 py-2 text-sm text-title">
                                            {{ $visualCard['reply_preview'] }}
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @else
                            @if (!empty($visualCard['heading']) || !empty($visualCard['badge']))
                                <div class="f-between">
                                    @if (!empty($visualCard['heading']))
                                        <p class="s-text text-neutral-500">{{ $visualCard['heading'] }}</p>
                                    @endif
                                    @if (!empty($visualCard['badge']))
                                        <span class="badge badge-soft">{{ $visualCard['badge'] }}</span>
                                    @endif
                                </div>
                            @endif
                            @if (!empty($visualCard['progress_value']))
                                <p class="mt-2 font-title text-3xl font-extrabold text-title">{{ $visualCard['progress_value'] }}</p>
                            @endif
                            @if (!empty($visualCard['progress_percentage']))
                                <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-neutral-100">
                                    <span class="block h-full rounded-full bg-primary" style="width: {{ $visualCard['progress_percentage'] }}"></span>
                                </div>
                            @endif
                            @if (!empty($visualCard['progress_label']))
                                <p class="s-text mt-3 text-neutral-500">{{ $visualCard['progress_label'] }}</p>
                            @endif
                        @endif
                    </div>
                @endforeach
            @endif
        </div>

        <div class="spot-sticky order-1 lg:order-2" data-reveal>
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
    </div>
</section>
