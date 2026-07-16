<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Welcome') - {{ config('app.name', 'Admin Panel') }}</title>
    <script>
        (() => {
            const theme = localStorage.getItem('theme');
            const wantsDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', wantsDark);
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/regular/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/fill/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/bold/style.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-plugins.head-scripts />
</head>
<body class="min-h-screen flex items-center justify-center bg-neutral-50 dark:bg-neutral-0 p-4 antialiased">
    <div class="section-card w-full max-w-md">
        <!-- Logo -->
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl gradient-primary text-white shadow-lg shadow-primary/20">
                <i class="ph-bold ph-lightning text-2xl"></i>
            </div>
            <h1 class="text-xl font-bold text-neutral-950">{{ setting('site_name', config('app.name', 'Admin Panel')) }}</h1>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-success/30 bg-success/10 p-3 text-sm text-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-xl border border-error/30 bg-error/10 p-3 text-sm text-error">
                {{ session('error') }}
            </div>
        @endif

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </div>

    <div id="toastContainer" class="fixed top-4 right-4 z-[9999] flex flex-col gap-3"></div>
    <x-ui.flash />
</body>
</html>
