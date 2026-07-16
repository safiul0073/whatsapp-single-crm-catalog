@php
    $d           = $section->data ?? [];
    $eyebrow     = $d['eyebrow']     ?? __('How to Reach Us');
    $heading     = $d['heading']     ?? __('Multiple Ways to Get Help');
    $description = $d['description'] ?? __('Choose the channel that works best for you. Our team is trained to resolve issues quickly and keep you informed every step of the way.');

    $channels = $d['channels'] ?? [
        [
            'icon'        => 'message-circle',
            'color'       => 'blue',
            'title'       => __('Live Chat'),
            'description' => __('Chat with our support agents in real time. Fastest response for urgent issues and quick questions.'),
            'features'    => __("Avg. response under 5 minutes\nScreen sharing available\nFile attachment supported"),
            'cta_text'    => __('Start a Chat'),
            'cta_link'    => '#',
        ],
        [
            'icon'        => 'mail',
            'color'       => 'green',
            'title'       => __('Submit a Ticket'),
            'description' => __("Open a formal support ticket for detailed issues. We track every ticket until it's fully resolved."),
            'features'    => __("Reply within 24 hours\nFull issue tracking & history\nPriority escalation available"),
            'cta_text'    => __('Open Ticket'),
            'cta_link'    => '#',
        ],
        [
            'icon'        => 'book-open',
            'color'       => 'navy',
            'title'       => __('Knowledge Base'),
            'description' => __('Browse our comprehensive documentation. Find step-by-step guides, tutorials, and answers 24/7.'),
            'features'    => __("200+ articles & tutorials\nVideo walkthroughs included\nUpdated weekly"),
            'cta_text'    => __('Browse Docs'),
            'cta_link'    => '#',
        ],
    ];

    $colorMap = [
        'blue'  => ['tile' => 'bg-tint-blue border-[rgba(33,72,255,0.18)] text-brand-blue',   'check' => 'bg-tint-blue text-brand-blue',   'link' => 'text-brand-blue',   'grad' => 'rgba(33,72,255,0.05)'],
        'green' => ['tile' => 'bg-tint-green border-[rgba(22,199,132,0.2)] text-brand-green', 'check' => 'bg-tint-green text-brand-green', 'link' => 'text-brand-green', 'grad' => 'rgba(22,199,132,0.06)'],
        'navy'  => ['tile' => 'bg-tint-navy border-[rgba(15,15,73,0.14)] text-brand-navy-ink','check' => 'bg-tint-navy text-brand-navy-ink','link' => 'text-brand-navy-ink','grad' => 'rgba(15,15,73,0.05)'],
    ];
@endphp

<section class="bg-white border-b border-border-soft py-12 lg:py-16 xl:py-20"
    aria-labelledby="support-channels-heading">
    <div class="section-container">
        <div class="grid gap-6 mb-12 lg:grid-cols-3 lg:gap-12 items-start">
            <div>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 pl-2.5 mb-3 bg-white/70 border border-border-soft rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue">
                    <span class="w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>{{ $eyebrow }}
                </span>
                <h2 id="support-channels-heading"
                    class="font-display text-[28px] md:text-[36px] lg:text-[44px] font-extrabold tracking-display leading-heading text-brand-navy-ink text-balance">
                    {{ $heading }}
                </h2>
            </div>
            <div class="hidden lg:block"></div>
            <div class="flex flex-col gap-5 lg:pt-2">
                <p class="font-body text-body text-text-muted leading-relaxed-body">{{ $description }}</p>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($channels as $channel)
                @php
                    $color   = $channel['color'] ?? 'blue';
                    $classes = $colorMap[$color] ?? $colorMap['blue'];
                    $bullets = array_filter(array_map('trim', explode("\n", $channel['features'] ?? '')));
                @endphp
                <div class="about-value-card rounded-2xl border border-border-soft bg-white px-6 py-6 shadow-xs"
                    style="background: radial-gradient(60% 80% at 0% 0%, {{ $classes['grad'] }}, transparent 60%), #fff;">
                    <div class="about-value-icon w-11 h-11 rounded-xl border inline-grid place-items-center mb-4 {{ $classes['tile'] }}">
                        <i data-lucide="{{ $channel['icon'] ?? 'circle' }}" class="w-5 h-5" aria-hidden="true"></i>
                    </div>
                    <h3 class="font-display font-bold text-brand-navy-ink text-body tracking-body">{{ $channel['title'] ?? '' }}</h3>
                    <p class="mt-2 font-body text-body-sm text-text-muted leading-relaxed-body">{{ $channel['description'] ?? '' }}</p>
                    @if ($bullets)
                        <ul class="mt-4 flex flex-col gap-2">
                            @foreach ($bullets as $bullet)
                                <li class="flex items-center gap-2 text-body-sm text-text-strong">
                                    <span class="w-4 h-4 rounded-pill inline-grid place-items-center flex-none {{ $classes['check'] }}">
                                        <i data-lucide="check" class="w-2.5 h-2.5" style="stroke-width:2.8" aria-hidden="true"></i>
                                    </span>
                                    {{ $bullet }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if (!empty($channel['cta_text']))
                        <a href="{{ $channel['cta_link'] ?? '#' }}"
                            class="mt-5 inline-flex items-center gap-1.5 font-body text-body-sm font-semibold hover:gap-2.5 transition-all duration-[var(--duration-base)] {{ $classes['link'] }}">
                            {{ $channel['cta_text'] }} <i data-lucide="arrow-right" class="w-4 h-4" aria-hidden="true"></i>
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
