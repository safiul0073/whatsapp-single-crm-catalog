@props(['showLinks' => true])

@php
    $activeLanguages = $activeLanguages ?? collect();
    $currentLocale = $currentLocale ?? app()->getLocale();
@endphp

<footer class="mt-auto border-t border-neutral-200 px-4 py-4 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-neutral-500">
            © {{ date('Y') }} {{ setting('site_name', config('app.name')) }}. {{ __('All rights reserved.') }}
        </p>

        <div class="flex flex-wrap items-center gap-4">
            @if($showLinks)
                <a href="{{ url('/support') }}" class="text-xs text-neutral-500 transition-colors hover:text-primary">{{ __('Support') }}</a>
                <a href="{{ route('frontend.page', 'privacy-policy') }}" class="text-xs text-neutral-500 transition-colors hover:text-primary">{{ __('Privacy') }}</a>
                <a href="{{ route('frontend.page', 'terms-and-conditions') }}" class="text-xs text-neutral-500 transition-colors hover:text-primary">{{ __('Terms') }}</a>
            @endif

            <x-ui.footer-language-switcher :languages="$activeLanguages" :current="$currentLocale" />
        </div>
    </div>
</footer>
