@php
    $d            = $section->data ?? [];
    $badgeLabel   = $d['badge_label']      ?? __('Support Team Ready');
    $heading      = $d['heading']          ?? __("Still Have Questions? We're Here to Help.");
    $description  = $d['description']      ?? __('Log in to access the full support portal or create an account to get started in under a minute.');
    $loginText    = $d['login_cta_text']   ?? __('Login to Support Portal');
    $registerText = $d['register_cta_text']?? __('Create Free Account');
@endphp

<section class="bg-white py-12 lg:py-16 xl:py-20" aria-label="{{ __('Get support') }}">
    <div class="section-container">
        <div class="relative rounded-3xl bg-brand-navy-ink overflow-hidden px-8 py-14 text-center lg:px-16 lg:py-20">
            <!-- Decoration -->
            <svg class="pointer-events-none absolute left-0 top-0 w-72 h-72 opacity-10" viewBox="0 0 288 288" fill="none" aria-hidden="true">
                <circle cx="0" cy="0" r="100" stroke="white" stroke-width="1.5" />
                <circle cx="0" cy="0" r="160" stroke="white" stroke-width="1.5" />
                <circle cx="0" cy="0" r="220" stroke="white" stroke-width="1.5" />
            </svg>
            <svg class="pointer-events-none absolute right-0 bottom-0 w-72 h-72 opacity-10" viewBox="0 0 288 288" fill="none" aria-hidden="true">
                <circle cx="288" cy="288" r="100" stroke="white" stroke-width="1.5" />
                <circle cx="288" cy="288" r="160" stroke="white" stroke-width="1.5" />
                <circle cx="288" cy="288" r="220" stroke="white" stroke-width="1.5" />
            </svg>

            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 rounded-pill border border-white/20 bg-white/10 px-4 py-2 mb-6">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-green animate-pulse"></span>
                    <span class="font-mono text-micro font-semibold tracking-[0.12em] uppercase text-white/80">{{ $badgeLabel }}</span>
                </div>
                <h2 class="font-display text-[clamp(28px,4vw,52px)] font-extrabold leading-tight-display tracking-display text-white text-balance max-w-[22ch] mx-auto">
                    {{ $heading }}
                </h2>
                <p class="mt-5 font-body text-body text-white/70 max-w-[48ch] mx-auto leading-relaxed-body">
                    {{ $description }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3 justify-center">
                    <a href="{{ route('login') }}"
                        class="inline-flex items-center gap-2 rounded-md bg-white px-6 py-3 font-body font-semibold text-body-sm text-brand-navy-ink shadow-md hover:-translate-y-px hover:shadow-lg transition-all duration-200">
                        {{ $loginText }}
                        <i data-lucide="arrow-right" class="w-4 h-4" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('login') }}"
                        class="inline-flex items-center gap-2 rounded-md border border-white/20 bg-white/10 px-6 py-3 font-body font-semibold text-body-sm text-white hover:bg-white/20 transition-all duration-200">
                        {{ $registerText }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
