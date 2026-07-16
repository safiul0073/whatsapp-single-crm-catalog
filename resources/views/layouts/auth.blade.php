<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Welcome') — {{ config('app.name', 'Classic') }}</title>
    <meta name="description" content="@yield('meta_description', 'Sign in to your ' . config('app.name', 'WaPro') . ' account to manage WhatsApp campaigns, inboxes, automations, and AI replies.')" />
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/brand/favicon.png') }}" />
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    @vite(['resources/css/classic/main.css', 'resources/js/classic/index.js'])
</head>

<body class="bg-bg-soft text-text-default min-h-screen">

    <!-- Reusable icon symbols -->
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5" />
            </symbol>
        </defs>
    </svg>

    <div class="min-h-screen lg:grid lg:grid-cols-[1fr_520px] xl:grid-cols-[1fr_580px]">

        @section('left_panel')
        <!-- ===== LEFT — Brand panel (login default) ===== -->
        <div class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-brand-navy-ink p-12 xl:p-16">

            <!-- Background decoration -->
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <span class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-brand-blue/20 blur-3xl"></span>
                <span class="absolute bottom-0 right-0 w-80 h-80 rounded-full bg-brand-green/10 blur-3xl"></span>
                <div class="absolute top-0 bottom-0 left-1/3 w-0 border-l border-dashed border-white/5"></div>
                <div class="absolute top-0 bottom-0 left-2/3 w-0 border-l border-dashed border-white/5"></div>
                <div class="absolute left-0 right-0 top-1/3 h-0 border-t border-dashed border-white/5"></div>
                <div class="absolute left-0 right-0 top-2/3 h-0 border-t border-dashed border-white/5"></div>
            </div>

            <!-- Top — Logo -->
            <div class="relative z-10">
                <a href="{{ url('/') }}" aria-label="Back to home">
                    <img src="{{ asset('assets/brand/logo_light.png') }}" class="h-8"
                        alt="{{ config('app.name', 'Classic') }}" />
                </a>
            </div>

            <!-- Middle — Feature highlights -->
            @php
                $ld = $authLoginSection->data ?? [];
                $ldEyebrow = $ld['eyebrow_text'] ?? 'WhatsApp SaaS';
                $ldHeadingOne = $ld['heading_line_one'] ?? 'Welcome back.';
                $ldAccent = $ld['heading_accent'] ?? "Let's pick up";
                $ldHeadingTwo = $ld['heading_line_two'] ?? 'where we left off.';
                $ldSubheading =
                    $ld['subheading'] ??
                    'Access campaigns, contacts, inbox, automations, and account settings — all in one place.';
                $ldFeatureOne = $ld['feature_one_text'] ?? 'Campaign and delivery dashboard';
                $ldFeatureTwo = $ld['feature_two_text'] ?? 'Shared inbox with AI suggestions';
                $ldFeatureThree = $ld['feature_three_text'] ?? 'Cloud API webhooks and developer tools';
            @endphp
            <div class="relative z-10 space-y-6 my-auto py-16">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 mb-4">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-green animate-pulse"></span>
                    <span class="font-mono text-micro font-semibold tracking-eyebrow uppercase text-white/80">{{ $ldEyebrow }}</span>
                </div>

                <h1 class="font-display text-[clamp(32px,3vw,48px)] font-extrabold leading-tight-display tracking-display text-white text-balance max-w-[18ch]">
                    {{ $ldHeadingOne }}<br />
                    <span class="text-brand-green">{{ $ldAccent }}</span><br />
                    {{ $ldHeadingTwo }}
                </h1>

                <p class="font-body text-body text-white/70 leading-relaxed-body max-w-[38ch]">
                    {{ $ldSubheading }}
                </p>

                <!-- Feature list -->
                <ul class="space-y-4 pt-2">
                    <li class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-xl bg-tint-blue/20 text-brand-blue inline-grid place-items-center flex-none">
                            <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                        </span>
                        <span class="font-body text-body-sm text-white/80">{{ $ldFeatureOne }}</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-xl bg-brand-green/20 text-brand-green inline-grid place-items-center flex-none">
                            <i data-lucide="headphones" class="w-4 h-4"></i>
                        </span>
                        <span class="font-body text-body-sm text-white/80">{{ $ldFeatureTwo }}</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-xl bg-white/10 text-white/70 inline-grid place-items-center flex-none">
                            <i data-lucide="file-text" class="w-4 h-4"></i>
                        </span>
                        <span class="font-body text-body-sm text-white/80">{{ $ldFeatureThree }}</span>
                    </li>
                </ul>
            </div>

            <!-- Bottom — Testimonial slider -->
            @if ($authTestimonials->isNotEmpty())
                <div class="relative z-10" x-data="{
                    current: 0,
                    total: {{ $authTestimonials->count() }},
                    next() { this.current = (this.current + 1) % this.total; }
                }" x-init="setInterval(() => next(), 5000)">

                    <div class="rounded-2xl border border-white/10 bg-white/5 backdrop-blur-sm p-6">

                        <div class="relative overflow-hidden" style="height:10rem">
                            @foreach ($authTestimonials as $index => $t)
                                <div x-show="current === {{ $index }}"
                                    x-transition:enter="transition ease-out duration-400"
                                    x-transition:enter-start="opacity-0 translate-x-8"
                                    x-transition:enter-end="opacity-100 translate-x-0"
                                    x-transition:leave="transition ease-in duration-200"
                                    x-transition:leave-start="opacity-100 translate-x-0"
                                    x-transition:leave-end="opacity-0 -translate-x-8" class="absolute inset-0"
                                    style="display:none">
                                    <div class="flex gap-0.5 mb-3">
                                        @for ($i = 0; $i < $t->rating; $i++)
                                            <i data-lucide="star" class="w-4 h-4 text-warning" style="fill:#f59e0b"></i>
                                        @endfor
                                    </div>
                                    <p class="font-body text-body-sm text-white/80 leading-relaxed-body italic">
                                        "{{ $t->quote }}"
                                    </p>
                                    <div class="flex items-center gap-3 mt-4">
                                        <div class="w-9 h-9 rounded-full bg-brand-blue/40 border border-white/20 flex items-center justify-center font-display font-bold text-white text-body-sm flex-none">
                                            {{ strtoupper(substr($t->client_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-display font-bold text-white text-body-sm">{{ $t->client_name }}</p>
                                            <p class="font-body text-micro text-white/50">{{ $t->designation }}
                                                @if ($t->company_name), {{ $t->company_name }}@endif
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                    </div>
                </div>
            @endif
        </div>
        @show

        <!-- ===== RIGHT — Form panel ===== -->
        <div class="relative flex flex-col min-h-screen lg:min-h-0">

            <!-- Decorative background (mobile only) -->
            <div class="fixed inset-0 pointer-events-none overflow-hidden lg:hidden" aria-hidden="true">
                <span class="absolute w-105 h-105 -top-20 -right-20 rounded-full opacity-40 bg-tint-blue"></span>
                <span class="absolute w-80 h-80 -bottom-16 -left-16 rounded-full opacity-35 bg-tint-green"></span>
            </div>

            <!-- Mobile logo -->
            <div class="flex items-center justify-between px-6 pt-6 lg:hidden relative">
                <a href="{{ url('/') }}" aria-label="Back to home">
                    <img src="{{ asset('assets/brand/logo_light.png') }}" class="h-7"
                        alt="{{ config('app.name', 'Classic') }}" />
                </a>
                <a href="{{ url('/') }}"
                    class="inline-flex items-center gap-1.5 font-body text-body-sm font-semibold text-text-muted hover:text-text-strong transition-colors">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    Home
                </a>
            </div>

            <!-- Form area -->
            <div class="flex-1 flex items-center justify-center px-6 py-12 lg:py-16 relative">
                <div class="w-full max-w-[420px]">

                    <!-- Back link (desktop) -->
                    <a href="{{ url('/') }}"
                        class="hidden lg:inline-flex items-center gap-1.5 font-body text-body-sm font-semibold text-text-muted hover:text-text-strong transition-colors mb-8">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to home
                    </a>

                    @yield('content')

                </div>
            </div>
        </div>

    </div>

</body>

</html>
