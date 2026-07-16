<section class="section">
    <div class="shell">
        <div class="card" style="border-color: rgba(220, 38, 38, 0.15);">
            <p class="eyebrow" style="color: #dc2626;">{{ __('Theme Fallback') }}</p>
            <h2 style="margin: 8px 0 12px; font-size: 1.5rem;">{{ __('This section is not supported by the current theme.') }}</h2>
            <p style="margin: 0; color: #6b7280; line-height: 1.7;">
                {{ __('Section type') }}: <strong>{{ config('frontend-sections.' . $section->type . '.label', $section->type) }}</strong>.
                {{ __('Add a theme-specific renderer or switch to a compatible theme to show this section publicly.') }}
            </p>
        </div>
    </div>
</section>
