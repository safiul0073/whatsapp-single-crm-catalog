<footer class="px-4 md:px-6 xl:px-8 py-5 border-t border-border-soft mt-auto">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <p class="font-body text-[12px] text-text-muted">© {{ date('Y') }}
            {{ setting('site_name', config('app.name')) }}. {{ __('All rights reserved.') }}</p>
        <div class="flex items-center gap-4">
            <a href="{{ url('/support') }}"
                class="font-body text-[12px] text-text-muted hover:text-brand-blue transition-colors">{{ __('Support') }}</a>
            <a href="{{ route('frontend.page', 'privacy-policy') }}"
                class="font-body text-[12px] text-text-muted hover:text-brand-blue transition-colors">{{ __('Privacy') }}</a>
            <a href="{{ route('frontend.page', 'terms-and-conditions') }}"
                class="font-body text-[12px] text-text-muted hover:text-brand-blue transition-colors">{{ __('Terms') }}</a>
        </div>
    </div>
</footer>
