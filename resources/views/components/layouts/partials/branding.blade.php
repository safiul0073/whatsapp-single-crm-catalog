{{-- Favicon --}}
@php($customFavicon = setting('site_favicon') && media_url(setting('site_favicon')) ? media_url(setting('site_favicon')) : null)
@if ($customFavicon)
<link rel="icon" href="{{ $customFavicon }}">
<link rel="apple-touch-icon" href="{{ $customFavicon }}">
@else
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
<link rel="icon" type="image/png" href="{{ asset('assets/brand/favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
@endif

{{-- Dynamic Theme Colors (overrides compiled Tailwind defaults) --}}
@if (setting('primary_color', '#5096f2') !== '#5096f2' || setting('secondary_color', '#6366f1') !== '#6366f1')
    <style>
        :root,
        .dark {
            @if (setting('primary_color', '#5096f2') !== '#5096f2')
                --color-primary: {{ setting('primary_color') }};
            @endif
            @if (setting('secondary_color', '#6366f1') !== '#6366f1')
                --color-secondary: {{ setting('secondary_color') }};
            @endif
        }
    </style>
@endif
