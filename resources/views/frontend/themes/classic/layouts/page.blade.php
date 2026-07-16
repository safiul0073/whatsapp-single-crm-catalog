<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $page->meta_title ?: $page->title)</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/brand/favicon.png') }}">
    @hasSection('meta_description')
        <meta name="description" content="@yield('meta_description')">
    @elseif(!empty($page->meta_description))
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    @stack('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/regular/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/bold/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phosphor/fill/style.css') }}">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    @vite(['resources/css/classic/main.css', 'resources/js/classic/index.js', 'resources/css/wapro/home.css', 'resources/js/wapro/home.js'])
    <link rel="stylesheet" href="{{ route('frontend.theme-css', ['theme' => $themeKey]) }}">
    <x-plugins.head-scripts />
</head>

<body>
    @includeFirst(
        [$theme['view_namespace'] . '.navigation.header', 'frontend.shared.navigation.header'],
        [
            'theme' => $theme,
            'themeVars' => $themeVars,
            'resolvedMenus' => $resolvedMenus,
            'headerServiceCards' => $headerServiceCards ?? [],
        ]
    )

    <main id="main" class="site-main" tabindex="-1">
        @hasSection('main')
            @yield('main')
        @else
            @foreach ($resolvedSections as $resolved)
                @include($resolved['view'], [
                    'section' => $resolved['section'],
                    'themeKey' => $themeKey,
                    'themeVars' => $themeVars,
                    'supported' => $resolved['supported'],
                    'jobPostings' => $jobPostings ?? null,
                ])
            @endforeach
        @endif
    </main>

    @includeFirst(
        [$theme['view_namespace'] . '.navigation.footer', 'frontend.shared.navigation.footer'],
        ['theme' => $theme, 'themeVars' => $themeVars, 'resolvedMenus' => $resolvedMenus]
    )

    @if(isset($page) && ($page->slug ?? null) === 'home')
        @include('frontend.themes.classic.pages.partials.home_video_modal')
    @endif

    @include('frontend.shared.cookie-consent')

    @stack('scripts')
</body>

</html>
