@props([
    'title' => 'Dashboard',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $currentDirection ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} - {{ config('app.name', 'Admin Panel') }}</title>

    <script>
        (() => {
            const theme = localStorage.getItem('theme');
            const wantsDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', wantsDark);
        })();
    </script>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

    {{-- Phosphor Icons --}}
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/regular/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/bold/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/fill/style.css') }}">

    {{-- Vite Assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Additional Styles --}}
    @stack('styles')

    {{-- Branding: favicon + dynamic theme colors --}}
    @include('components.layouts.partials.branding')
</head>
<body class="overflow-x-hidden bg-section text-body antialiased">

    {{-- Impersonation Banner --}}
    <x-ui.impersonation-banner />

    {{-- Sidebar Navigation --}}
    <x-navigation.sidebar />

    {{-- Main Content --}}
    <section id="mainContent" class="app-shell">

        {{-- Topbar --}}
        <x-navigation.topbar :title="$title" />

        {{-- Page Content --}}
        <main class="px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </section>

    {{-- Drawer Overlay --}}
    <div id="drawerOverlay" class="drawer-overlay"></div>

    {{-- Drawers --}}
    @stack('drawers')

    {{-- Toast Notification Container --}}
    <x-ui.toast />

    {{-- Flash Messages --}}
    <x-ui.flash />

    {{-- Global Search Modal --}}
    <x-ui.global-search />

    {{-- Modals --}}
    <x-media.modal />
    <x-ui.global-confirm />
    @stack('modals')

    {{-- Additional Scripts --}}
    @stack('scripts')
</body>
</html>
