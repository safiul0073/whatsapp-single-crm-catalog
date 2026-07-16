@php
    $d = $section->data ?? [];
    $heading = $d['section_heading'] ?? __("Let's Talk About Your Project");
    $subheading =
        $d['section_subheading'] ??
        __("Share your project idea and we'll get back to you within one business day with a free scoping estimate — no obligation.");
    $email = $d['email'] ?? 'hello.com';
    $whatsappLink = $d['whatsapp_link'] ?? 'https://wa.me/000000000';
    $calLink = $d['cal_link'] ?? 'pixelaxis/idea-sprint';
    $calNamespace = $d['cal_namespace'] ?? 'idea-sprint';
    $calElementId = 'global-cal-inline-' . $section->id;
@endphp
<section id="contact" class="bg-white border-b border-border-soft py-12 lg:py-16 xl:py-20"
    aria-labelledby="global-contact-heading-{{ $section->id }}">
    <div class="section-container">
        <div class="grid gap-12 lg:grid-cols-2 lg:gap-16 items-start">
            <div>
                <span
                    class="inline-flex items-center gap-2 px-3 py-1.5 pl-2.5 mb-4 bg-white/70 border border-border-soft rounded-pill font-mono text-micro font-semibold tracking-[0.14em] uppercase text-brand-blue">
                    <span class="w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>{{ __('Get in Touch') }}
                </span>
                <h2 id="global-contact-heading-{{ $section->id }}"
                    class="font-display text-[28px] md:text-[36px] lg:text-[44px] font-extrabold tracking-display leading-heading text-brand-navy-ink text-balance">
                    {{ $heading }}
                </h2>
                <p class="mt-4 font-body text-body-sm text-text-muted leading-relaxed-body max-w-prose">
                    {{ $subheading }}
                </p>

                <div class="mt-8 flex flex-col gap-3.5">
                    <a href="mailto:{{ $email }}"
                        class="global-contact-card group flex items-center gap-4 rounded-xl border border-border-soft bg-bg-soft px-5 py-4 no-underline transition-[border-color,background-color,transform] duration-200 [transition-timing-function:var(--ease-out-soft)] hover:border-brand-blue hover:bg-white hover:-translate-y-0.5">
                        <span
                            class="w-10 h-10 rounded-xl bg-tint-blue text-brand-blue inline-grid place-items-center flex-none transition-transform duration-200 group-hover:scale-105"><i
                                data-lucide="mail" class="w-4 h-4"></i></span>
                        <div class="min-w-0">
                            <p class="font-display font-semibold text-text-strong text-body-sm tracking-body">
                                {{ __('Email Us') }}</p>
                            <p class="text-text-muted text-micro mt-0.5">{{ $email }}</p>
                        </div>
                        <i data-lucide="arrow-up-right"
                            class="w-4 h-4 ml-auto flex-none text-text-light transition-colors duration-200 group-hover:text-brand-blue"></i>
                    </a>
                    <a href="{{ $whatsappLink }}" target="_blank" rel="noopener"
                        class="global-contact-card group flex items-center gap-4 rounded-xl border border-border-soft bg-bg-soft px-5 py-4 no-underline transition-[border-color,background-color,transform] duration-200 [transition-timing-function:var(--ease-out-soft)] hover:border-brand-green hover:bg-white hover:-translate-y-0.5">
                        <span
                            class="w-10 h-10 rounded-xl bg-tint-green text-brand-green inline-grid place-items-center flex-none transition-transform duration-200 group-hover:scale-105"><i
                                data-lucide="message-circle" class="w-4 h-4"></i></span>
                        <div class="min-w-0">
                            <p class="font-display font-semibold text-text-strong text-body-sm tracking-body">
                                WhatsApp</p>
                            <p class="text-text-muted text-micro mt-0.5">{{ __('Chat with our team directly') }}</p>
                        </div>
                        <i data-lucide="arrow-up-right"
                            class="w-4 h-4 ml-auto flex-none text-text-light transition-colors duration-200 group-hover:text-brand-green"></i>
                    </a>
                    <a href="#{{ $calElementId }}"
                        class="global-contact-card group flex items-center gap-4 rounded-xl border border-border-soft bg-bg-soft px-5 py-4 no-underline transition-[border-color,background-color,transform] duration-200 [transition-timing-function:var(--ease-out-soft)] hover:border-brand-navy-ink hover:bg-white hover:-translate-y-0.5">
                        <span
                            class="w-10 h-10 rounded-xl bg-tint-navy text-brand-navy-ink inline-grid place-items-center flex-none transition-transform duration-200 group-hover:scale-105"><i
                                data-lucide="calendar" class="w-4 h-4"></i></span>
                        <div class="min-w-0">
                            <p class="font-display font-semibold text-text-strong text-body-sm tracking-body">{{ __('Book a Call') }}</p>
                            <p class="text-text-muted text-micro mt-0.5">{{ __('Free 30-min consultation') }}</p>
                        </div>
                        <i data-lucide="arrow-up-right"
                            class="w-4 h-4 ml-auto flex-none text-text-light transition-colors duration-200 group-hover:text-brand-navy-ink"></i>
                    </a>
                </div>

                <!-- Trust strip -->
                <div class="mt-8 pt-7 border-t border-border-soft grid grid-cols-3 gap-4">
                    <div class="flex flex-col gap-1">
                        <span
                            class="inline-flex items-center gap-1.5 font-display text-[22px] font-extrabold leading-none text-brand-navy-ink tabular-nums">
                            <i data-lucide="clock" class="w-4 h-4 text-brand-blue"></i>&lt;24h
                        </span>
                        <span
                            class="font-mono text-micro font-semibold tracking-[0.1em] uppercase text-text-muted">{{ __('Response time') }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span
                            class="inline-flex items-center gap-1.5 font-display text-[22px] font-extrabold leading-none text-brand-navy-ink tabular-nums">
                            <i data-lucide="star" class="w-4 h-4 text-warning fill-warning"></i>4.9<span
                                class="text-text-light text-[0.6em] self-end mb-0.5">/5</span>
                        </span>
                        <span
                            class="font-mono text-micro font-semibold tracking-[0.1em] uppercase text-text-muted">{{ __('Client rating') }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span
                            class="inline-flex items-center gap-1.5 font-display text-[22px] font-extrabold leading-none text-brand-navy-ink tabular-nums">
                            <i data-lucide="shield-check" class="w-4 h-4 text-brand-green"></i>NDA
                        </span>
                        <span class="font-mono text-micro font-semibold tracking-[0.1em] uppercase text-text-muted">{{ __('On request') }}</span>
                    </div>
                </div>
            </div>

            <!-- Contact form -->
            <div class="global-cal-card overflow-hidden rounded-2xl border border-border-soft bg-white shadow-md">
                <!-- Header bar -->
                <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-border-soft bg-bg-soft">
                    <div class="flex items-center gap-3">
                        <span
                            class="w-10 h-10 rounded-xl bg-brand-blue text-white inline-grid place-items-center flex-none shadow-xs">
                            <i data-lucide="calendar-clock" class="w-5 h-5"></i>
                        </span>
                        <div>
                            <h3
                                class="font-display font-bold text-brand-navy-ink text-body tracking-body leading-tight">
                                {{ __('Book a Free Consultation') }}</h3>
                            <p class="text-text-muted text-micro mt-0.5">{{ __('Pick a 30-min slot that works for you') }}</p>
                        </div>
                    </div>
                    <span
                        class="hidden sm:inline-flex items-center gap-1.5 rounded-pill bg-tint-green px-2.5 py-1 font-mono text-micro font-semibold tracking-[0.1em] uppercase text-brand-green">
                        <span class="w-1.5 h-1.5 rounded-pill bg-brand-green"></span>{{ __('Free') }}
                    </span>
                </div>
                <!-- Cal inline embed code begins -->
                <div class="global-cal-embed w-full min-h-[700px] sm:min-h-[560px] overflow-hidden"
                    id="{{ $calElementId }}">
                </div>
                <script type="text/javascript">
                    (function (C, A, L) {
                        let p = function (a, ar) {
                            a.q.push(ar);
                        };
                        let d = C.document;
                        C.Cal = C.Cal || function () {
                            let cal = C.Cal;
                            let ar = arguments;
                            if (!cal.loaded) {
                                cal.ns = {};
                                cal.q = cal.q || [];
                                d.head.appendChild(d.createElement("script")).src = A;
                                cal.loaded = true;
                            }
                            if (ar[0] === L) {
                                const api = function () {
                                    p(api, arguments);
                                };
                                const namespace = ar[1];
                                api.q = api.q || [];
                                if (typeof namespace === "string") {
                                    cal.ns[namespace] = cal.ns[namespace] || api;
                                    p(cal.ns[namespace], ar);
                                    p(cal, ["initNamespace", namespace]);
                                } else p(cal, ar);
                                return;
                            }
                            p(cal, ar);
                        };
                    })(window, "https://app.cal.com/embed/embed.js", "init");
                    Cal("init", @json($calNamespace), {
                        origin: "https://app.cal.com"
                    });
                    Cal.config = Cal.config || {};
                    Cal.config.forwardQueryParams = true;

                    Cal.ns[@json($calNamespace)]("inline", {
                        elementOrSelector: "#" + @json($calElementId),
                        config: {
                            layout: "month_view",
                            useSlotsViewOnSmallScreen: true,
                            theme: "light",
                        },
                        calLink: @json($calLink),
                    });

                    Cal.ns[@json($calNamespace)]("ui", {
                        theme: "light",
                        hideEventTypeDetails: false,
                    });
                </script>
                <!-- Cal inline embed code ends -->
            </div>
        </div>
    </div>
</section>
