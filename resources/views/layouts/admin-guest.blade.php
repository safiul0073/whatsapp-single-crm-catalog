<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $currentDirection ?? 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Admin Login')) - {{ config('app.name', 'Admin Panel') }}</title>
    <script>
        (() => {
            const theme = localStorage.getItem('theme');
            const wantsDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', wantsDark);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/regular/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/fill/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/bold/style.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen flex antialiased bg-neutral-50 text-neutral-900">

    {{-- Left: Branding Panel (Mesh Gradients + Glassmorphism Grid) --}}
    <div
        class="hidden lg:flex lg:w-1/2 flex-col items-center justify-center bg-gradient-to-br from-neutral-950 via-neutral-900 to-neutral-950 p-16 relative overflow-hidden">
        
        {{-- Mesh Gradients --}}
        <div class="absolute inset-0 z-0">
            <div class="absolute top-[-10%] left-[-10%] w-[60%] h-[60%] rounded-full bg-primary/20 blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[60%] h-[60%] rounded-full bg-secondary/15 blur-[120px]"></div>
        </div>

        {{-- Grid Backdrop --}}
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff02_1px,transparent_1px),linear-gradient(to_bottom,#ffffff02_1px,transparent_1px)] bg-[size:32px_32px] z-1"></div>

        <div class="relative z-10 text-center max-w-md w-full">
            {{-- Company Light Logo --}}
            <div class="relative mx-auto mb-8 flex items-center justify-center">
                <div class="absolute -inset-4 bg-primary/25 rounded-full blur-xl animate-pulse"></div>
                <img src="{{ asset('assets/brand/logo_light.png') }}" alt="{{ setting('site_name', config('app.name', 'Admin Panel')) }}" class="relative h-12 w-auto object-contain">
            </div>

            <h1 class="text-3xl font-extrabold text-white mb-3 tracking-[-0.03em] leading-tight">
                {{ setting('site_name', config('app.name', 'Admin Panel')) }}
            </h1>
            <p class="text-neutral-400 text-[14.5px] leading-relaxed max-w-sm mx-auto">
                {{ __('Secure administration portal. Access restricted to authorized personnel only.') }}
            </p>

            {{-- Frosted Glass Feature List --}}
            <div class="mt-10 bg-white/[0.02] border border-white/[0.06] backdrop-blur-md p-6 rounded-2xl space-y-4 text-left shadow-2xl shadow-black/20">
                <div class="flex items-center gap-4 text-neutral-300 group hover:translate-x-1 transition-transform">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/[0.05] border border-white/10 shadow-sm text-primary">
                        <i class="ph-bold ph-lock text-base"></i>
                    </div>
                    <span class="text-sm font-medium tracking-wide">{{ __('Multi-guard Authentication') }}</span>
                </div>
                <div class="flex items-center gap-4 text-neutral-300 group hover:translate-x-1 transition-transform">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/[0.05] border border-white/10 shadow-sm text-primary">
                        <i class="ph-bold ph-shield-check text-base"></i>
                    </div>
                    <span class="text-sm font-medium tracking-wide">{{ __('Role-based Access Control') }}</span>
                </div>
                <div class="flex items-center gap-4 text-neutral-300 group hover:translate-x-1 transition-transform">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/[0.05] border border-white/10 shadow-sm text-primary">
                        <i class="ph-bold ph-clipboard-text text-base"></i>
                    </div>
                    <span class="text-sm font-medium tracking-wide">{{ __('Audit Log Tracking') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Auth Form --}}
    <div class="flex w-full lg:w-1/2 items-center justify-center bg-white p-6 sm:p-12 relative">
        {{-- Subtle Grid Pattern for Form side too --}}
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#00000002_1px,transparent_1px),linear-gradient(to_bottom,#00000002_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none"></div>

        <div class="w-full max-w-md relative z-10">
            {{-- Mobile Logo (hidden on desktop) --}}
            <div class="mb-10 flex flex-col items-center gap-3 lg:hidden">
                <div class="relative w-14 h-14 flex items-center justify-center">
                    <div class="absolute inset-0 bg-primary/20 rounded-2xl blur-lg"></div>
                    <div class="relative flex h-12 w-12 items-center justify-center rounded-xl gradient-primary text-white shadow-md">
                        <i class="ph-bold ph-shield-check text-2xl"></i>
                    </div>
                </div>
                <h1 class="text-xl font-black tracking-tight text-neutral-950 mt-1">
                    {{ setting('site_name', config('app.name', 'Admin Panel')) }}
                </h1>
            </div>

            {{-- Alert System --}}
            @if (session('success'))
                <div class="mb-6 rounded-2xl border border-success/20 bg-success/5 p-4 text-sm text-success flex items-start gap-3 shadow-[0_2px_8px_rgba(0,0,0,0.01)]">
                    <i class="ph-bold ph-check-circle text-lg shrink-0 mt-0.5"></i>
                    <p class="leading-relaxed">{{ session('success') }}</p>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-2xl border border-error/20 bg-error/5 p-4 text-sm text-error flex items-start gap-3 shadow-[0_2px_8px_rgba(0,0,0,0.01)]">
                    <i class="ph-bold ph-warning-circle text-lg shrink-0 mt-0.5"></i>
                    <p class="leading-relaxed">{{ session('error') }}</p>
                </div>
            @endif

            @if (session('status'))
                <div class="mb-6 rounded-2xl border border-primary/20 bg-primary/5 p-4 text-sm text-primary flex items-start gap-3 shadow-[0_2px_8px_rgba(0,0,0,0.01)]">
                    <i class="ph-bold ph-info text-lg shrink-0 mt-0.5"></i>
                    <p class="leading-relaxed">{{ session('status') }}</p>
                </div>
            @endif

            <div class="bg-white rounded-2xl lg:border lg:border-neutral-100 lg:p-8 lg:shadow-[0_4px_24px_rgba(0,0,0,0.02)]">
                @yield('content')
            </div>
        </div>
    </div>

    <div id="toastContainer" class="fixed top-4 right-4 z-[9999] flex flex-col gap-3"></div>
    <x-ui.flash />
</body>

</html>
