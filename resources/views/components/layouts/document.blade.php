@props(['title', 'back' => null, 'backLabel' => null, 'format' => 'a4'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-section text-title print:bg-white">
    <main class="document-sheet document-sheet-{{ $format }}">
        <div class="document-toolbar">
            @if($back)
                <a href="{{ $back }}" class="inline-flex items-center gap-1 text-sm text-body hover:text-title"><i class="ph ph-arrow-left"></i> {{ $backLabel ?? __('Back to order') }}</a>
            @else
                <span></span>
            @endif
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions ?? '' }}
                <button type="button" class="btn btn-primary btn-sm" id="printDocument"><i class="ph ph-printer"></i> {{ __('Print') }}</button>
            </div>
        </div>
        {{ $slot }}
    </main>
    <script>document.getElementById('printDocument').addEventListener('click', function () { 'use strict'; window.print(); }); if (new URLSearchParams(window.location.search).get('print') === '1') { window.addEventListener('load', function () { 'use strict'; window.print(); }); }</script>
</body>
</html>
