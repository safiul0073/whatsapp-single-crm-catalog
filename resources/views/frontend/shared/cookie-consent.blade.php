@php
    $enabled = (bool) setting('cookie_popup_enabled', true);
    $title = setting('cookie_popup_title', __('We use cookies'));
    $message = setting('cookie_popup_message', __('We use cookies to improve your browsing experience, analyze site traffic, and personalize content. By clicking accept, you consent to our use of cookies.'));
    $acceptLabel = setting('cookie_popup_accept_label', __('Accept'));
    $policyLabel = setting('cookie_popup_policy_label', __('Cookie Policy'));
    $policyUrl = setting('cookie_popup_policy_url', '/cookie-policy') ?: '/cookie-policy';
    $lifetimeDays = max(1, (int) setting('cookie_popup_lifetime_days', 365));
@endphp

@if ($enabled)
    <div
        class="cookie-consent"
        data-cookie-consent
        data-cookie-name="wapro_cookie_consent"
        data-cookie-lifetime-days="{{ $lifetimeDays }}"
        hidden
    >
        <div class="cookie-consent__panel" role="dialog" aria-live="polite" aria-label="{{ $title }}">
            <div class="cookie-consent__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M11.77 3.02a1 1 0 0 1 1.06.69 3.5 3.5 0 0 0 4.19 2.33 1 1 0 0 1 1.15 1.23 3.5 3.5 0 0 0 2.12 4.26 1 1 0 0 1 .65 1.1A9.35 9.35 0 1 1 11.77 3.02Zm-.42 2.05a7.35 7.35 0 1 0 7.5 7.5 5.52 5.52 0 0 1-2.83-4.46 5.52 5.52 0 0 1-4.67-3.04Z" />
                    <path d="M8.25 9.5a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Zm5 7a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Zm-4.5-.75a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm5-5.75a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
                </svg>
            </div>

            <div class="cookie-consent__body">
                <div class="cookie-consent__heading">
                    <p class="cookie-consent__title">{{ $title }}</p>
                    <span class="cookie-consent__badge">{{ __('GDPR') }}</span>
                </div>
                <p class="cookie-consent__message">{{ $message }}</p>
            </div>

            <div class="cookie-consent__actions">
                <a href="{{ $policyUrl }}" class="cookie-consent__link">{{ $policyLabel }}</a>
                <button type="button" class="cookie-consent__button" data-cookie-consent-accept>
                    {{ $acceptLabel }}
                </button>
            </div>
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                (function () {
                    'use strict';

                    function getCookie(name) {
                        return document.cookie
                            .split('; ')
                            .find((row) => row.startsWith(name + '='))
                            ?.split('=')[1];
                    }

                    function setCookie(name, value, lifetimeDays) {
                        const maxAge = Math.max(1, Number(lifetimeDays) || 365) * 24 * 60 * 60;
                        document.cookie = `${name}=${value}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
                    }

                    function initCookieConsent() {
                        'use strict';

                        const banner = document.querySelector('[data-cookie-consent]');
                        if (!banner) return;

                        const name = banner.dataset.cookieName || 'wapro_cookie_consent';
                        const accepted = getCookie(name) === 'accepted' || localStorage.getItem(name) === 'accepted';
                        if (accepted) return;

                        banner.hidden = false;
                        banner.querySelector('[data-cookie-consent-accept]')?.addEventListener('click', function () {
                            'use strict';

                            setCookie(name, 'accepted', banner.dataset.cookieLifetimeDays || 365);
                            localStorage.setItem(name, 'accepted');
                            banner.hidden = true;
                        });
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initCookieConsent);
                    } else {
                        initCookieConsent();
                    }
                })();
            </script>
        @endpush
    @endonce
@endif
