@php
    $d              = $section->data ?? [];
    $eyebrow        = $d['eyebrow']          ?? __('Why Choose Us');
    $heading        = $d['heading']          ?? __('Support You Can Actually Rely On');
    $description    = $d['description']      ?? __("We don't just answer tickets — we solve problems. Our team is trained, empowered, and committed to your success.");
    $statOneValue   = $d['stat_one_value']   ?? '98%';
    $statOneLabel   = $d['stat_one_label']   ?? __('Satisfaction rate');
    $statTwoValue   = $d['stat_two_value']   ?? '< 5 min';
    $statTwoLabel   = $d['stat_two_label']   ?? __('Avg. first reply');
    $rating         = $d['rating']           ?? '4.9';
    $ratingLabel    = $d['rating_label']     ?? __('Average Support Rating');
    $ratingCount    = $d['rating_count']     ?? __('Based on 2,800+ reviews');
    $ticketsResolved= $d['tickets_resolved'] ?? '10K+';
    $happyClients   = $d['happy_clients']    ?? '23K+';

    $featureCards = $d['feature_cards'] ?? [
        ['icon' => 'zap',         'color' => 'blue',  'title' => __('Fast First Response'),      'description' => __('Live chat replies in under 5 minutes during business hours. Tickets acknowledged within 1 hour.'),           'position' => 'left'],
        ['icon' => 'shield-check','color' => 'green', 'title' => __('Secure & Private'),         'description' => __('All support interactions are encrypted. Your project data is never shared or accessed without permission.'), 'position' => 'left'],
        ['icon' => 'users',       'color' => 'blue',  'title' => __('Dedicated Account Support'), 'description' => __('Enterprise clients get a dedicated account manager who knows your project inside and out.'),                'position' => 'right'],
        ['icon' => 'globe',       'color' => 'green', 'title' => __('Multi-Language Support'),   'description' => __('Our team speaks English, Arabic, and Bengali — serving clients across Asia, the Middle East, and beyond.'), 'position' => 'right'],
    ];

    $leftCards  = array_values(array_filter($featureCards, fn ($c) => ($c['position'] ?? 'left') === 'left'));
    $rightCards = array_values(array_filter($featureCards, fn ($c) => ($c['position'] ?? 'left') === 'right'));

    $tileMap = [
        'blue'  => 'bg-tint-blue text-brand-blue',
        'green' => 'bg-tint-green text-brand-green',
        'navy'  => 'bg-tint-navy text-brand-navy-ink',
    ];
@endphp

<section class="bg-bg-soft border-b border-border-soft py-12 lg:py-16 xl:py-20"
    aria-labelledby="support-why-heading">
    <div class="section-container">
        <div class="grid gap-6 mb-10 lg:grid-cols-3 lg:gap-12 items-start">
            <div>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 pl-2.5 mb-3 bg-white/70 border border-border-soft rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue">
                    <span class="w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>{{ $eyebrow }}
                </span>
                <h2 id="support-why-heading"
                    class="font-display text-[28px] md:text-[36px] lg:text-[44px] font-extrabold tracking-display leading-heading text-brand-navy-ink text-balance">
                    {{ $heading }}
                </h2>
            </div>
            <div class="hidden lg:block"></div>
            <div class="flex flex-col gap-5 lg:pt-2">
                <p class="font-body text-body text-text-muted leading-relaxed-body">{{ $description }}</p>
                <div class="flex items-center gap-5">
                    <div>
                        <p class="font-display text-[26px] font-extrabold leading-none text-brand-navy-ink tabular-nums">{{ $statOneValue }}</p>
                        <p class="mt-1 font-mono text-micro font-semibold tracking-[0.12em] uppercase text-text-muted">{{ $statOneLabel }}</p>
                    </div>
                    <span class="w-px h-9 bg-border-default" aria-hidden="true"></span>
                    <div>
                        <p class="font-display text-[26px] font-extrabold leading-none text-brand-navy-ink tabular-nums">{{ $statTwoValue }}</p>
                        <p class="mt-1 font-mono text-micro font-semibold tracking-[0.12em] uppercase text-text-muted">{{ $statTwoLabel }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3-col: left cards | center stat panel | right cards -->
        <div class="grid gap-4 lg:grid-cols-[1fr_360px_1fr] xl:grid-cols-[1fr_400px_1fr] items-stretch">

            <!-- Left cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 content-start">
                @foreach ($leftCards as $card)
                    @php $tile = $tileMap[$card['color'] ?? 'blue'] ?? $tileMap['blue']; @endphp
                    <div class="bg-white rounded-2xl p-6 shadow-xs">
                        <div class="w-14 h-14 rounded-2xl inline-grid place-items-center mb-5 {{ $tile }}">
                            <i data-lucide="{{ $card['icon'] ?? 'circle' }}" class="w-6 h-6" aria-hidden="true"></i>
                        </div>
                        <h3 class="font-display font-bold text-brand-navy-ink text-body tracking-body">{{ $card['title'] ?? '' }}</h3>
                        <p class="mt-2 font-body text-body-sm text-text-muted leading-relaxed-body">{{ $card['description'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>

            <!-- Center: support stats panel -->
            <div class="rounded-2xl overflow-hidden shadow-md min-h-85 lg:min-h-0 bg-brand-navy-ink relative flex flex-col justify-between p-8">
                <svg class="pointer-events-none absolute right-0 top-0 w-48 h-48 opacity-10" viewBox="0 0 192 192" fill="none" aria-hidden="true">
                    <circle cx="192" cy="0" r="80" stroke="white" stroke-width="1.5" />
                    <circle cx="192" cy="0" r="120" stroke="white" stroke-width="1.5" />
                    <circle cx="192" cy="0" r="160" stroke="white" stroke-width="1.5" />
                </svg>

                <div class="relative z-10">
                    <div class="inline-flex items-center gap-2 rounded-pill bg-white/10 border border-white/20 px-3 py-1.5 mb-6">
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-green animate-pulse"></span>
                        <span class="font-mono text-micro font-semibold tracking-[0.12em] uppercase text-white/80">{{ __('Live Support') }}</span>
                    </div>
                    <p class="font-display font-extrabold text-white leading-none" style="font-size:3rem;">
                        {{ $rating }}<span class="text-white/50 text-2xl">/5</span>
                    </p>
                    <p class="font-display font-bold text-white/90 text-body mt-2">{{ $ratingLabel }}</p>
                    <div class="flex gap-1 mt-3" aria-label="{{ __('5 out of 5 stars') }}">
                        @for ($i = 0; $i < 5; $i++)
                            <i data-lucide="star" class="w-5 h-5 text-warning" style="fill:#f59e0b" aria-hidden="true"></i>
                        @endfor
                    </div>
                    <p class="mt-2 font-body text-body-sm text-white/60">{{ $ratingCount }}</p>
                </div>

                <div class="relative z-10 mt-8 grid grid-cols-2 gap-4">
                    <div class="rounded-xl bg-white/10 border border-white/10 p-4">
                        <p class="font-display font-extrabold text-white text-h3 leading-none">{{ $ticketsResolved }}</p>
                        <p class="mt-1 font-body text-body-sm text-white/70">{{ __('Tickets Resolved') }}</p>
                    </div>
                    <div class="rounded-xl bg-white/10 border border-white/10 p-4">
                        <p class="font-display font-extrabold text-white text-h3 leading-none">{{ $happyClients }}</p>
                        <p class="mt-1 font-body text-body-sm text-white/70">{{ __('Happy Clients') }}</p>
                    </div>
                </div>
            </div>

            <!-- Right cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 content-start">
                @foreach ($rightCards as $card)
                    @php $tile = $tileMap[$card['color'] ?? 'blue'] ?? $tileMap['blue']; @endphp
                    <div class="bg-white rounded-2xl p-6 shadow-xs">
                        <div class="w-14 h-14 rounded-2xl inline-grid place-items-center mb-5 {{ $tile }}">
                            <i data-lucide="{{ $card['icon'] ?? 'circle' }}" class="w-6 h-6" aria-hidden="true"></i>
                        </div>
                        <h3 class="font-display font-bold text-brand-navy-ink text-body tracking-body">{{ $card['title'] ?? '' }}</h3>
                        <p class="mt-2 font-body text-body-sm text-text-muted leading-relaxed-body">{{ $card['description'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
