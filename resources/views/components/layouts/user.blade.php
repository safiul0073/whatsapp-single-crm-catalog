@props([
    'title' => 'Dashboard',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} — {{ config('app.name', 'WhatsApp Workspace') }}</title>

    <script>
        (() => {
            const theme = localStorage.getItem('theme');
            const wantsDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', wantsDark);
        })();
    </script>

    {{-- Google Fonts: Manrope + Inter + JetBrains Mono --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">

    {{-- Phosphor Icons --}}
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/regular/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/bold/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/fill/style.css') }}">

    {{-- Vite Assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Branding --}}
    @include('components.layouts.partials.branding')

    {{-- Additional Styles --}}
    @stack('styles')
</head>

<body class="min-h-screen overflow-x-hidden bg-section text-body antialiased">

    {{-- Impersonation Banner --}}
    <x-ui.impersonation-banner />

    {{-- Sidebar --}}
    @include('components.layouts.partials.user-sidebar')

    {{-- Main Wrapper --}}
    <div class="app-shell">

        {{-- Topbar --}}
        @include('components.layouts.partials.user-topbar', ['title' => $title])

        {{-- Main Content --}}
        <main class="px-4 py-6 sm:px-6 lg:px-8">
            <x-ui.page-help :page-title="$title" />
            {{ $slot }}
        </main>
    </div>

    {{-- Toast & Flash --}}
    <x-ui.toast />
    <x-ui.flash />

    {{-- Media Library Modal (used by x-media.picker) --}}
    @include('components.layouts.partials.media-library-modal')

    {{-- Global Search Modal --}}
    <x-ui.global-search />

    {{-- Modals & Drawers --}}
    @stack('modals')
    @stack('drawers')

    @stack('scripts')
</body>

</html>
