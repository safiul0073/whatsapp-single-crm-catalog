<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->meta_title ?: $page->title }}</title>
    @if($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    @stack('head')
    <x-plugins.head-scripts />
</head>
<body>
    @foreach($resolvedSections as $resolved)
        @include($resolved['view'], ['section' => $resolved['section'], 'themeKey' => $themeKey, 'themeVars' => $themeVars, 'supported' => $resolved['supported']])
    @endforeach

    @include('frontend.shared.cookie-consent')

    @stack('scripts')
</body>
</html>
