@php $d = $section->data ?? []; @endphp
<section class="spy-section bg-section">
    <div class="container">
        <div class="mx-auto max-w-2xl text-center">
            @if (! empty($d['eyebrow']))
                <span class="eyebrow">{{ $d['eyebrow'] }}</span>
            @endif
            @if (! empty($d['heading']))
                <h2 class="heading-1 mt-4">{{ $d['heading'] }}</h2>
            @endif
        </div>

        @php $cases = $d['cases'] ?? []; @endphp
        @if (! empty($cases))
            <div class="mt-14 space-y-12 lg:mt-20 lg:space-y-24">
                @foreach ($cases as $case)
                    @php
                        $mockup = $case['mockup_data'] ?? [];
                        $lineList = fn (mixed $value): array => is_array($value)
                            ? array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''))
                            : array_values(array_filter(preg_split('/\r\n|\r|\n/', (string) $value), fn ($item) => trim($item) !== ''));
                        $layoutDirection = $case['layout_direction'] ?? 'text_left';
                        $isTextRight = in_array($layoutDirection, ['right', 'text_right'], true);
                        $textOrderClass = $isTextRight ? 'lg:order-last' : '';
                        $messages = ! empty($case['messages']) ? $lineList($case['messages']) : ($mockup['messages'] ?? []);
                        $mockupType = $case['visual_type'] ?? (! empty($messages)
                            ? 'chatbot'
                            : (! empty($case['delivered']) || ! empty($case['change']) || ! empty($mockup['delivered']) || ! empty($mockup['change']) ? 'performance' : 'campaign'));
                        $cardBackgroundClass = $mockupType === 'chatbot' ? 'bg-section' : 'bg-neutral-0';
                        $stats = $mockup['stats'] ?? [];
                        $flatStats = [
                            ['value' => $case['stat_1_value'] ?? null, 'label' => $case['stat_1_label'] ?? null],
                            ['value' => $case['stat_2_value'] ?? null, 'label' => $case['stat_2_label'] ?? null],
                            ['value' => $case['stat_3_value'] ?? null, 'label' => $case['stat_3_label'] ?? null],
                        ];
                        if (collect($flatStats)->contains(fn ($stat) => filled($stat['value']) || filled($stat['label']))) {
                            $stats = $flatStats;
                        }
                        $bullets = $lineList($case['bullets'] ?? []);
                    @endphp
                    <div data-reveal class="grid items-center gap-8 lg:grid-cols-2 lg:gap-16">
                        <div class="{{ $textOrderClass }}">
                            @if (! empty($case['eyebrow']))
                                <span class="eyebrow">{{ $case['eyebrow'] }}</span>
                            @endif
                            @if (! empty($case['title']))
                                <h3 class="heading-2 mt-4">{{ $case['title'] }}</h3>
                            @endif
                            @if (! empty($case['description']))
                                <p class="lead-text mt-4">{{ $case['description'] }}</p>
                            @endif
                            @if (! empty($bullets))
                                <ul class="mt-6 space-y-3">
                                    @foreach ($bullets as $bullet)
                                        <li class="flex items-start gap-3">
                                            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary text-neutral-0"><svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                                            <span class="l-text text-title">{{ $bullet }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if (! empty($case['link_text']))
                                <a href="{{ $case['link_url'] ?? route('features') }}" class="mt-7 inline-flex items-center gap-1.5 text-sm font-semibold text-primary transition-all hover:gap-2.5">{{ $case['link_text'] }}
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                                </a>
                            @endif
                        </div>

                        <div class="rounded-3xl border border-neutral-200 {{ $cardBackgroundClass }} p-6 shadow-[0_30px_70px_-40px_rgba(10,27,20,0.35)]">
                            @if ($mockupType === 'chatbot')
                                <div class="flex items-center gap-2">
                                    <span class="grid h-8 w-8 place-items-center rounded-full bg-primary text-neutral-0"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z"/></svg></span>
                                    <div>
                                        <p class="text-xs font-bold text-title">{{ $case['bot_name'] ?? $mockup['bot_name'] ?? __('Auto-reply bot') }}</p>
                                        <p class="text-[10px] text-primary">{{ $case['status'] ?? $mockup['status'] ?? __('online') }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 space-y-1.5">
                                    @foreach ($messages as $message)
                                        <div @class([
                                            'rounded-lg px-2.5 py-1.5 text-[11px] text-title',
                                            'max-w-[80%] rounded-tl-sm bg-neutral-0 shadow-1' => $loop->first,
                                            'ml-auto max-w-[85%] rounded-tr-sm bg-[#dcf8c6]' => ! $loop->first && ! $loop->last,
                                            'ml-auto max-w-[55%] rounded-tr-sm bg-[#dcf8c6]' => $loop->last,
                                        ])>{{ $message }}</div>
                                    @endforeach
                                </div>
                            @elseif ($mockupType === 'performance')
                                <div class="f-between">
                                    <div>
                                        <p class="s-text text-neutral-500">{{ __('Messages delivered') }}</p>
                                        <p class="font-title text-3xl font-extrabold text-title">{{ $case['delivered'] ?? $mockup['delivered'] ?? '104.7k' }}</p>
                                    </div>
                                    <span class="badge badge-soft">{{ $case['change'] ?? $mockup['change'] ?? '+78%' }}</span>
                                </div>
                                <div class="mt-6 flex h-32 items-end gap-3">
                                    <div class="h-[40%] flex-1 rounded-t-lg bg-neutral-100"></div>
                                    <div class="h-[30%] flex-1 rounded-t-lg bg-neutral-100"></div>
                                    <div class="h-[95%] flex-1 rounded-t-lg bg-primary"></div>
                                    <div class="h-[55%] flex-1 rounded-t-lg bg-neutral-100"></div>
                                    <div class="h-[70%] flex-1 rounded-t-lg bg-primary/60"></div>
                                </div>
                            @else
                                <div class="f-between">
                                    <p class="s-text font-semibold text-title">{{ $case['campaign_name'] ?? $mockup['campaign_name'] ?? $mockup['title'] ?? __('Campaign · Spring Sale') }}</p>
                                    <span class="badge badge-soft">{{ $case['status'] ?? $mockup['status'] ?? __('Sent') }}</span>
                                </div>
                                <div class="mt-5 grid grid-cols-3 gap-3 text-center">
                                    @foreach (($stats ?: [['value' => '42.1k', 'label' => __('Sent')], ['value' => '86%', 'label' => __('Read')], ['value' => '11.4%', 'label' => __('Replies')]]) as $stat)
                                        <div class="rounded-xl {{ $loop->last ? 'bg-primary/10' : 'bg-section' }} p-3">
                                            <p class="font-title text-xl font-bold {{ $loop->last ? 'text-primary' : 'text-title' }}">{{ $stat['value'] ?? '' }}</p>
                                            <p class="s-text">{{ $stat['label'] ?? '' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
