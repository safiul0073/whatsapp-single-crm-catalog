<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $currentDirection ?? 'ltr' }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title') - {{ config('app.name', 'Admin Panel') }}</title>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

    {{-- Phosphor Icons --}}
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/regular/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/bold/style.css') }}">

    {{-- Vite Assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center bg-neutral-50 dark:bg-neutral-0 p-4 antialiased">

    <section class="section-card w-full max-w-md">
        <div class="flex flex-col items-center text-center p-8">

            {{-- Icon --}}
            <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-2xl @yield('icon-bg', 'bg-error/10')">
                <i class="ph-bold @yield('icon', 'ph-warning') text-4xl @yield('icon-color', 'text-error')"></i>
            </div>

            {{-- Error Code --}}
            <p class="mb-2 text-sm font-bold uppercase tracking-wider text-neutral-400">
                @yield('code')
            </p>

            {{-- Title --}}
            <h1 class="mb-3 text-2xl font-bold text-neutral-950">
                @yield('title')
            </h1>

            {{-- Message --}}
            <p class="mb-8 text-neutral-500">
                @yield('message')
            </p>

            {{-- Actions --}}
            <div class="flex gap-3">
                <a href="{{ url('/') }}" class="btn btn-primary">
                    <i class="ph ph-house me-2"></i>
                    {{ __('Go Home') }}
                </a>
                <button onclick="history.back()" class="btn btn-outline">
                    <i class="ph ph-arrow-left me-2"></i>
                    {{ __('Go Back') }}
                </button>
            </div>
        </div>
    </section>

</body>
</html>
