@php
    $recaptchaEnabled = (bool) setting('plugin_turnstile_enabled', false);
    $recaptchaSiteKey = trim((string) setting('plugin_turnstile_site_key', ''));
@endphp

@if($recaptchaEnabled && $recaptchaSiteKey !== '')
    <div class="space-y-2">
        <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
        <p class="text-xs text-neutral-400">{{ __('If the verification box does not load, check that the Google reCAPTCHA v2 site key is valid for this domain.') }}</p>
    </div>
    @error('g-recaptcha-response')
        <p class="form-error">{{ $message }}</p>
    @enderror
    @once
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endonce
@endif
