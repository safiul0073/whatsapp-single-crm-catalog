@php
    $d             = $section->data ?? [];
    $eyebrow       = $d['eyebrow']          ?? __('Support');
    $badgeLabel    = $d['badge_label']       ?? __('Classic Help Center');
    $heading       = $d['heading']           ?? __('Welcome to Classic Support.');
    $description   = $d['description']       ?? __('We put special emphasis on customer support. Our dedicated support team is waiting to assist you. We always try to give you a better support experience.');
    $hoursdays     = $d['hours_days']        ?? __('Saturday to Thursday');
    $hoursTime     = $d['hours_time']        ?? __('5:00AM – 2:00PM (GMT)');
    $hoursHoliday  = $d['hours_holiday']     ?? __('Friday is our weekly holiday!');
    $accessNote    = $d['access_note']       ?? __('To keep our support system efficient and seamless and to keep your data safe and secure, we only keep this page accessible for registered users.');
    $loginText     = $d['login_cta_text']    ?? __('Login');
    $registerText  = $d['register_cta_text'] ?? __('Register');
@endphp

<section class="relative isolate overflow-hidden bg-bg-soft border-b border-border-default pt-32 pb-0 -mt-28"
    aria-labelledby="support-hero-heading">
    <!-- Dashed grid lines -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_78%,transparent_100%)]"
        aria-hidden="true">
        <div class="absolute top-0 bottom-0 left-1/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-1/2 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute top-0 bottom-0 left-3/4 w-0 border-l border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[160px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[320px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
        <div class="absolute left-0 right-0 top-[480px] h-0 border-t border-dashed border-[rgba(17,18,74,0.1)]"></div>
    </div>
    <!-- Tinted block accents -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden [mask-image:linear-gradient(180deg,#000_0%,#000_78%,transparent_100%)]"
        aria-hidden="true">
        <span class="absolute bg-tint-blue top-0 left-1/2 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-green/60 top-[160px] left-1/4 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-blue/70 top-[320px] right-0 w-1/4 h-[160px]"></span>
        <span class="absolute bg-tint-navy/30 top-[480px] left-0 w-1/4 h-[160px]"></span>
    </div>

    <div class="section-container relative z-10 grid items-center gap-12 pt-14 pb-16 lg:grid-cols-2 lg:gap-10 xl:grid-cols-5 xl:gap-16">

        <!-- LEFT — Copy -->
        <div class="xl:col-span-2">
            <div class="inline-flex items-center gap-2.5 rounded-pill border border-border-soft bg-white/70 py-1.5 pl-2 pr-3 backdrop-blur-md shadow-hero-eyebrow mb-6">
                <span class="inline-flex items-center gap-1.5 rounded-pill bg-tint-blue px-2.5 py-1 text-micro font-bold uppercase tracking-eyebrow text-brand-blue">
                    <i data-lucide="headphones" class="w-3 h-3" aria-hidden="true"></i>
                    {{ $eyebrow }}
                </span>
                <span class="text-caption font-medium text-text-default">{{ $badgeLabel }}</span>
                <span class="text-text-light"><i data-lucide="arrow-right" class="h-3 w-3" aria-hidden="true"></i></span>
            </div>

            <h1 id="support-hero-heading"
                class="mt-6 max-w-[20ch] font-display text-[36px] font-extrabold leading-tight-display tracking-display text-balance text-brand-navy-ink md:text-[40px] lg:text-[45px] xl:text-[55px]">
                {{ $heading }}
            </h1>

            <p class="mt-5 max-w-xl font-body text-body-lg leading-body text-text-muted">
                {{ $description }}
            </p>

            <!-- Support hours card -->
            <div class="mt-8 inline-flex items-start gap-5 rounded-2xl border border-border-soft bg-white/90 backdrop-blur-sm px-6 py-5 shadow-sm">
                <div class="w-11 h-11 rounded-xl bg-tint-blue text-brand-blue inline-grid place-items-center flex-none mt-0.5">
                    <i data-lucide="clock" class="w-5 h-5" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="font-display font-bold text-text-strong text-body tracking-body">{{ __('Support Time') }}</p>
                    <div class="mt-2 h-px bg-border-soft"></div>
                    <p class="mt-2 font-body font-bold text-text-strong text-body-sm">{{ $hoursdays }}</p>
                    <p class="font-body font-bold text-text-strong text-body-sm">{{ $hoursTime }}</p>
                    @if ($hoursHoliday)
                        <p class="mt-1 font-body text-body-sm font-semibold text-brand-blue">{{ $hoursHoliday }}</p>
                    @endif
                </div>
            </div>

            @if ($accessNote)
                <p class="mt-6 max-w-[48ch] font-body text-body-sm text-text-muted leading-relaxed-body">
                    {{ $accessNote }}
                </p>
            @endif

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('login') }}"
                    class="inline-flex items-center gap-2.5 rounded-md border border-white/15 bg-gradient-to-b from-brand-blue to-primary-hover px-5.5 py-3.75 text-sm font-semibold text-white shadow-hero-cta transition-all duration-200">
                    {{ $loginText }}
                    <i data-lucide="arrow-right" class="w-4 h-4" aria-hidden="true"></i>
                </a>
                <a href="{{ route('login') }}"
                    class="inline-flex items-center gap-2.5 rounded-md border border-border-default bg-white px-5 py-3.5 text-sm font-semibold text-text-strong transition-all duration-200">
                    {{ $registerText }}
                </a>
            </div>
        </div>

        <!-- RIGHT — Illustration -->
        <div class="xl:col-span-3 relative self-stretch max-lg:hidden">
            <div class="relative h-full min-h-[420px] flex items-center justify-center">
                <div class="relative w-full max-w-[480px] mx-auto">
                    <!-- Background blob -->
                    <div class="absolute inset-0 rounded-full bg-gradient-to-br from-tint-blue via-tint-green/40 to-tint-blue opacity-80 blur-3xl scale-90"></div>

                    <!-- Central support agent card -->
                    <div class="relative z-10 flex flex-col items-center">
                        <!-- Agent avatar -->
                        <div class="relative w-48 h-48 rounded-full bg-gradient-to-br from-tint-blue to-tint-navy border-4 border-white shadow-xl overflow-hidden flex items-end justify-center">
                            <div class="w-32 h-36 rounded-full bg-gradient-to-b from-[#f4a261] to-[#e76f51] relative flex flex-col items-center">
                                <!-- Head -->
                                <div class="w-20 h-20 rounded-full bg-[#f4a261] border-2 border-white/20 mt-2 relative">
                                    <!-- Headset -->
                                    <div class="absolute -top-1 -left-2 w-24 h-10 border-t-4 border-l-4 border-r-4 border-brand-navy-ink/60 rounded-t-full"></div>
                                    <div class="absolute -bottom-1 -left-3 w-3 h-5 bg-brand-navy-ink/60 rounded-sm"></div>
                                    <!-- Face -->
                                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-3">
                                        <div class="w-2 h-2 rounded-full bg-brand-navy-ink/40"></div>
                                        <div class="w-2 h-2 rounded-full bg-brand-navy-ink/40"></div>
                                    </div>
                                    <div class="absolute bottom-1 left-1/2 -translate-x-1/2 w-6 h-1.5 border-b-2 border-brand-navy-ink/30 rounded-full"></div>
                                </div>
                                <!-- Shirt -->
                                <div class="w-full h-16 bg-[#7c3aed] rounded-t-none rounded-b-none"></div>
                            </div>
                        </div>

                        <!-- Laptop -->
                        <div class="relative -mt-4 z-20 w-56 h-32 bg-gradient-to-b from-slate-200 to-slate-300 rounded-xl border border-slate-400/40 shadow-lg flex items-center justify-center">
                            <div class="w-44 h-24 bg-brand-navy-ink rounded-lg flex items-center justify-center">
                                <div class="space-y-1.5 w-32">
                                    <div class="h-1.5 bg-brand-blue rounded-full w-full"></div>
                                    <div class="h-1.5 bg-brand-blue/60 rounded-full w-4/5"></div>
                                    <div class="h-1.5 bg-tint-blue/60 rounded-full w-3/4"></div>
                                    <div class="h-1.5 bg-brand-green/60 rounded-full w-full"></div>
                                    <div class="h-1.5 bg-brand-blue/40 rounded-full w-2/3"></div>
                                </div>
                            </div>
                            <div class="absolute -bottom-3 left-0 right-0 h-3 bg-slate-300 rounded-b-xl border-t border-slate-400/30"></div>
                            <div class="absolute -bottom-4 left-8 right-8 h-1.5 bg-slate-400/40 rounded-full"></div>
                        </div>
                    </div>

                    <!-- Floating chat bubbles -->
                    <div class="absolute top-6 left-0 w-14 h-14 rounded-2xl bg-brand-green shadow-md flex items-center justify-center animate-[hero-float-slow_6s_ease-in-out_infinite]">
                        <i data-lucide="check" class="w-7 h-7 text-white" style="stroke-width:3" aria-hidden="true"></i>
                    </div>
                    <div class="absolute top-2 right-4 w-14 h-14 rounded-2xl bg-[#ef4444] shadow-md flex items-center justify-center animate-[hero-float-mid_5.4s_ease-in-out_infinite]">
                        <i data-lucide="alert-circle" class="w-7 h-7 text-white" aria-hidden="true"></i>
                    </div>
                    <div class="absolute top-1/2 -translate-y-1/2 -left-6 w-14 h-14 rounded-2xl bg-[#f59e0b] shadow-md flex items-center justify-center animate-[hero-float-slow_7s_ease-in-out_infinite_1s]">
                        <i data-lucide="help-circle" class="w-7 h-7 text-white" aria-hidden="true"></i>
                    </div>
                    <div class="absolute top-1/2 -translate-y-8 right-0 w-36 rounded-2xl bg-brand-blue shadow-md p-3 animate-[hero-float-mid_5.4s_ease-in-out_infinite_0.5s]">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center flex-none">
                                <i data-lucide="check" class="w-3 h-3 text-white" style="stroke-width:3" aria-hidden="true"></i>
                            </div>
                            <div class="space-y-1 flex-1">
                                <div class="h-1.5 bg-white/70 rounded-full w-full"></div>
                                <div class="h-1.5 bg-white/50 rounded-full w-3/4"></div>
                            </div>
                        </div>
                        <div class="h-1.5 bg-white/40 rounded-full w-4/5"></div>
                    </div>
                </div>
            </div>

            <!-- Online badge -->
            <div class="absolute bottom-8 left-6 inline-flex items-center gap-2 bg-white/95 backdrop-blur-sm border border-border-soft rounded-pill px-4 py-2 shadow-md">
                <span class="w-2 h-2 rounded-pill bg-brand-green animate-pulse"></span>
                <span class="font-body font-semibold text-text-strong text-micro">{{ __('Support team online now') }}</span>
            </div>
        </div>
    </div>
</section>
