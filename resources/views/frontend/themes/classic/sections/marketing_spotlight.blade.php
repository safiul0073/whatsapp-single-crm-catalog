@php $d = $section->data ?? []; @endphp
<section id="spotlight" class="spy-section bg-section">
    <div class="container grid items-start gap-12 lg:grid-cols-2 lg:gap-16">
        <div class="spot-sticky">
            <div class="spot-panel">
                @php
                    $stats = $d['stats'] ?? [];
                    $statSent = $d['stat_sent'] ?? ($stats['sent'] ?? '104.7k');
                    $statBadge = $d['stat_badge'] ?? ($stats['badge'] ?? ($stats['change'] ?? '+78%'));
                    $statRead = $d['stat_read'] ?? ($stats['read'] ?? '86%');
                    $statCtr = $d['stat_ctr'] ?? ($stats['ctr'] ?? '11.4%');
                    $statFailed = $d['stat_failed'] ?? ($stats['failed'] ?? '0.04%');
                @endphp
                <div class="spot-vis is-active" data-vis="1">
                    <div class="f-between">
                        <div>
                            <p class="s-text text-neutral-500">{{ __('Messages delivered') }}</p>
                            <p class="font-title text-3xl font-extrabold text-title">{{ $statSent }}</p>
                        </div>
                        <span class="badge badge-soft ml-3 shrink-0">{{ $statBadge }}</span>
                    </div>
                    <div class="mt-5 flex h-36 min-w-0 items-end gap-3 overflow-hidden">
                        <div class="h-[40%] flex-1 rounded-t-lg bg-neutral-100"></div>
                        <div class="h-[30%] flex-1 rounded-t-lg bg-neutral-100"></div>
                        <div class="h-[95%] flex-1 rounded-t-lg bg-primary"></div>
                        <div class="h-[55%] flex-1 rounded-t-lg bg-neutral-100"></div>
                        <div class="h-[48%] flex-1 rounded-t-lg bg-neutral-100"></div>
                        <div class="h-[70%] flex-1 rounded-t-lg bg-primary/60"></div>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-3 border-t border-neutral-100 pt-4 text-center">
                        <div><p class="font-title text-lg font-bold text-title">{{ $statRead }}</p><p class="s-text">{{ __('Read') }}</p></div>
                        <div><p class="font-title text-lg font-bold text-title">{{ $statCtr }}</p><p class="s-text">{{ __('CTR') }}</p></div>
                        <div><p class="font-title text-lg font-bold text-title">{{ $statFailed }}</p><p class="s-text">{{ __('Failed') }}</p></div>
                    </div>
                </div>

                <div class="spot-vis" data-vis="2">
                    <p class="s-text mb-4 font-semibold text-title">{{ __('Per-recipient activity') }}</p>
                    <div class="space-y-2.5">
                        @php $recipients = $d['recipients'] ?? []; @endphp
                        @foreach ($recipients as $recipient)
                            <div class="f-between rounded-xl bg-section px-3 py-2.5">
                                <span class="text-sm font-medium text-title">{{ $recipient['name'] ?? '' }}</span>
                                @if (!empty($recipient['status']))
                                    @if ($recipient['status'] === 'Replied')
                                        <span class="badge badge-soft">{{ __('Replied') }}</span>
                                    @else
                                        <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">{{ $recipient['status'] }}</span>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="spot-vis" data-vis="3">
                    <p class="s-text mb-4 font-semibold text-title">{{ __('A/B test · winner auto-picked') }}</p>
                    <div class="space-y-4">
                        <div class="rounded-xl border border-primary/30 bg-primary/5 p-4">
                            <div class="f-between mb-2">
                                <span class="text-sm font-bold text-title">{{ __('Variant A') }}</span>
                                <span class="badge badge-soft">{{ __('Winner') }}</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-full w-[72%] rounded-full bg-primary"></div>
                            </div>
                            <p class="s-text mt-1.5">{{ __('72% reply rate') }}</p>
                        </div>
                        <div class="rounded-xl border border-neutral-200 p-4">
                            <div class="f-between mb-2">
                                <span class="text-sm font-bold text-title">{{ __('Variant B') }}</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-full w-[41%] rounded-full bg-neutral-300"></div>
                            </div>
                            <p class="s-text mt-1.5">{{ __('41% reply rate') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="spot-steps">
            @if (!empty($d['eyebrow']))
                <span class="eyebrow">{{ $d['eyebrow'] }}</span>
            @endif
            @if (!empty($d['heading']))
                <h2 class="heading-1 mt-4">{{ $d['heading'] }}</h2>
            @endif
            @if (!empty($d['subheading']))
                <p class="lead-text mt-4">{{ $d['subheading'] }}</p>
            @endif

            <div class="mt-8 space-y-8 lg:mt-10 lg:space-y-14">
                @php $steps = $d['steps'] ?? []; @endphp
                @foreach ($steps as $index => $step)
                    <article class="spot-step {{ $index === 0 ? 'is-active' : '' }}" data-step="{{ $index + 1 }}">
                        <h3 class="heading-3">{{ $step['title'] ?? '' }}</h3>
                        <p class="l-text mt-3">{{ $step['description'] ?? '' }}</p>
                    </article>
                @endforeach
            </div>

            @if (!empty($d['cta_text']))
                <a href="{{ $d['cta_url'] ?? route('features') }}" class="btn btn-primary mt-10">{{ $d['cta_text'] }}</a>
            @endif
        </div>
    </div>
</section>
