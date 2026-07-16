@php
    $d = $section->data ?? [];
    $phrases = collect($d['phrases'] ?? [
        ['text' => 'Build with intent'],
        ['text' => 'Ship like a senior team'],
        ['text' => 'Design that survives engineering'],
        ['text' => 'Engineered for the way it grows'],
        ['text' => 'From discovery to launch'],
    ])->filter(fn($p) => !empty($p['text']))->values();
    $phrasesReversed = $phrases->reverse()->values();
    $badgeText = $d['badge_text'] ?? 'SOFTIVUS · BUILD · SHIP · SOFTIVUS · BUILD · SHIP ·';
@endphp

<section class="mq2 relative overflow-hidden isolate bg-white py-[clamp(60px,8vw,120px)]"
    aria-label="Manifesto marquee">

    <!-- Geometric architectural backdrop -->
    <div class="mq2-bg absolute inset-0 z-0 pointer-events-none" aria-hidden="true">
        <span class="mq2-bg-grid absolute inset-0"></span>
        <span
            class="mq2-bg-block absolute rounded-[4px] border border-[rgba(17,18,74,0.08)] bg-[rgba(33,72,255,0.045)] top-[12%] left-[6%] w-[clamp(160px,14vw,260px)] aspect-[4/3]"></span>
        <span
            class="mq2-bg-block absolute rounded-[4px] border border-[rgba(17,18,74,0.08)] bg-[rgba(22,199,132,0.055)] bottom-[14%] left-[22%] w-[clamp(120px,10vw,200px)] aspect-[3/2]"></span>
        <span
            class="mq2-bg-block absolute rounded-[4px] border border-[rgba(17,18,74,0.08)] bg-[rgba(33,72,255,0.04)] top-[18%] right-[18%] w-[clamp(100px,8vw,160px)] aspect-square"></span>
        <svg class="absolute w-20 h-20 text-[rgba(17,18,74,0.16)] top-[clamp(16px,3vw,36px)] left-[clamp(16px,3vw,36px)]"
            viewBox="0 0 80 80" aria-hidden="true">
            <path d="M80 0 L80 28 L52 28 L52 56 L24 56 L24 80" fill="none" stroke="currentColor"
                stroke-width="1" />
        </svg>
        <svg class="absolute w-20 h-20 text-[rgba(17,18,74,0.16)] bottom-[clamp(16px,3vw,36px)] right-[clamp(16px,3vw,36px)]"
            viewBox="0 0 80 80" aria-hidden="true">
            <path d="M0 80 L0 52 L28 52 L28 24 L56 24 L56 0" fill="none" stroke="currentColor"
                stroke-width="1" />
        </svg>
    </div>

    <!-- Row 1 — outline words, scrolling left -->
    <div class="mq2-row mq2-row--top relative z-10 flex overflow-hidden pb-1.5" aria-hidden="true">
        <div class="will-change-transform flex flex-none whitespace-nowrap mq2-track--ltr">
            @foreach ([1, 2] as $_)
                <div class="flex items-center flex-none gap-[clamp(28px,3vw,56px)] pr-[clamp(28px,3vw,56px)]">
                    @foreach ($phrases as $phrase)
                        <span
                            class="mq2-word mq2-word--outline font-display font-extrabold leading-none text-[clamp(56px,11vw,168px)] uppercase tracking-[-0.04em] flex-none text-transparent">{{ $phrase['text'] }}</span>
                        <span
                            class="flex-none w-3.5 h-3.5 rounded-pill border border-[rgba(15,15,73,0.18)] bg-transparent"></span>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <!-- Row 2 — solid words, scrolling right -->
    <div class="mq2-row mq2-row--bottom relative z-10 flex overflow-hidden pt-1.5" aria-hidden="true">
        <div class="will-change-transform flex flex-none whitespace-nowrap mq2-track--rtl">
            @foreach ([1, 2] as $_)
                <div class="flex items-center flex-none gap-[clamp(28px,3vw,56px)] pr-[clamp(28px,3vw,56px)]">
                    @foreach ($phrasesReversed as $phrase)
                        <span
                            class="mq2-word mq2-word--solid font-display font-extrabold leading-none text-[clamp(56px,11vw,168px)] uppercase tracking-[-0.04em] flex-none">{{ $phrase['text'] }}</span>
                        <span class="flex-none w-3.5 h-3.5 rounded-pill bg-[rgba(15,15,73,0.10)]"></span>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <!-- Floating circular badge -->
    <a href="#contact"
        class="mq2-badge max-xl:hidden absolute top-1/2 -translate-y-1/2 right-[clamp(24px,6vw,88px)] w-[clamp(120px,12vw,168px)] h-[clamp(120px,12vw,168px)] inline-grid place-items-center z-20 no-underline text-brand-navy-ink"
        aria-label="Get in touch with Classic">
        <svg class="mq2-badge-ring absolute inset-0 w-full h-full text-[rgba(15,15,73,0.85)]" viewBox="0 0 200 200"
            aria-hidden="true">
            <defs>
                <path id="mq2-circle" d="M 100 22 a 78 78 0 1 1 0 156 a 78 78 0 1 1 0 -156 Z" />
            </defs>
            <circle cx="100" cy="100" r="78" fill="none" stroke="currentColor" stroke-opacity=".22"
                stroke-width="1" />
            <circle cx="100" cy="100" r="64" fill="none" stroke="currentColor" stroke-opacity=".22"
                stroke-width="1" stroke-dasharray="2 6" />
            <text
                class="mq2-badge-text font-display font-bold text-[14px] tracking-[0.18em] uppercase fill-current">
                <textPath href="#mq2-circle" startOffset="0">{{ $badgeText }}</textPath>
            </text>
            <g stroke="currentColor" stroke-opacity=".4" stroke-width="1" stroke-linecap="round">
                <line x1="100" y1="14" x2="100" y2="20" />
                <line x1="186" y1="100" x2="180" y2="100" />
                <line x1="100" y1="186" x2="100" y2="180" />
                <line x1="14" y1="100" x2="20" y2="100" />
            </g>
        </svg>
        <span
            class="mq2-badge-core relative z-10 w-16 h-16 rounded-pill inline-grid place-items-center bg-gradient-to-br from-brand-blue to-[#1A3CE5] shadow-brand">
            <img src="{{ asset('assets/brand/logo_dark.png') }}" width="32" height="32" alt="Classic"
                aria-hidden="true" />
        </span>
    </a>

</section>
