@php
    $footerMenu = $resolvedMenus['footer'] ?? null;
    $footerItems = $footerMenu['items'] ?? [];

    $groups = [];
    $ungrouped = [];

    foreach ($footerItems as $item) {
        if (! empty($item['children'])) {
            $groups[] = $item;
        } else {
            $ungrouped[] = $item;
        }
    }

    $productGroup = collect($groups)->first(fn ($g) => strcasecmp($g['label'], 'Product') === 0);
    $companyGroup = collect($groups)->first(fn ($g) => strcasecmp($g['label'], 'Company') === 0);
    $legalFooterUrl = function (string $label, ?string $url = null): string {
        if (filled($url) && $url !== '#') {
            return $url;
        }

        return match (strtolower($label)) {
            'privacy policy' => route('frontend.page', 'privacy-policy'),
            'terms & conditions' => route('frontend.page', 'terms-and-conditions'),
            'confidentiality & privacy' => route('frontend.page', 'confidentiality-privacy'),
            'legal information' => route('frontend.page', 'legal-information'),
            'cookie policy' => route('frontend.page', 'cookie-policy'),
            default => $url ?: '#',
        };
    };

    $confidentialityPrivacyUrl = $legalFooterUrl('Confidentiality & Privacy', $themeVars['footer_link_privacy'] ?? null);
    $legalInformationUrl = $legalFooterUrl('Legal Information', $themeVars['footer_link_terms'] ?? null);
    $cookiePolicyUrl = $legalFooterUrl('Cookie Policy', $themeVars['footer_link_cookies'] ?? null);
    $footerPhone = (string) ($themeVars['footer_phone'] ?? '');
    $footerPhoneDigits = preg_replace('/\D+/', '', $footerPhone) ?: '';
    $footerPhoneHref = str_starts_with(trim($footerPhone), '+') && $footerPhoneDigits !== ''
        ? '+'.$footerPhoneDigits
        : $footerPhoneDigits;
@endphp

<footer class="bg-deep text-neutral-0">
  <div class="container">
    <div class="grid grid-cols-1 gap-y-12 py-16 lg:grid-cols-[1.15fr_0.85fr_0.85fr_1.15fr] lg:gap-x-12 lg:py-24">
      <div class="lg:pr-10">
        <a href="{{ route('home') }}" class="mb-10 inline-flex items-center gap-2.5">
          <span class="grid h-9 w-9 place-items-center rounded-lg bg-accent text-deep">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zM9.5 7a.5.5 0 0 1 .5.5c0 .7.1 1.4.3 2 .1.3 0 .6-.2.8l-.8.9a8.5 8.5 0 0 0 3.5 3.5l.9-.8c.2-.2.5-.3.8-.2.6.2 1.3.3 2 .3a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5A9 9 0 0 1 7 12a.5.5 0 0 1 .5-.5h2z"/></svg>
          </span>
          <span class="font-title text-2xl font-extrabold tracking-tight">{{ $themeVars['logo_text'] ?? 'WaPro' }}</span>
        </a>

        <h3 class="mb-4 text-sm font-bold tracking-[0.14em] uppercase">{{ __('Location') }}</h3>
        <p class="mb-9 text-base leading-relaxed text-neutral-0/55">
          {{ $themeVars['footer_address'] ?? '' }}
        </p>

        <h3 class="mb-4 text-sm font-bold tracking-[0.14em] uppercase">{{ __('Contact Us') }}</h3>
        <p class="text-base leading-relaxed text-neutral-0/55">
          @if ($footerPhone !== '' && $footerPhoneHref !== '')
            <a href="tel:{{ $footerPhoneHref }}" class="block transition-colors hover:text-neutral-0">{{ $footerPhone }}</a>
          @endif
          @if ($themeVars['footer_email'] ?? false)
            <a href="mailto:{{ $themeVars['footer_email'] }}" class="block transition-colors hover:text-neutral-0">{{ $themeVars['footer_email'] }}</a>
          @endif
        </p>
      </div>

      @if ($productGroup)
        <nav class="lg:pl-12" aria-label="{{ __('Product') }}">
          <h3 class="mb-7 text-sm font-bold tracking-[0.14em] uppercase">{{ $productGroup['label'] }}</h3>
          <ul class="flex flex-col gap-4 text-lg text-neutral-0/65">
            @foreach ($productGroup['children'] as $item)
              @if ($item['is_visible'] ?? true)
                <li><a href="{{ $legalFooterUrl($item['label'], $item['url'] ?? null) }}" class="transition-colors hover:text-neutral-0">{{ $item['label'] }}</a></li>
              @endif
            @endforeach
          </ul>
        </nav>
      @elseif (!empty($ungrouped))
        <nav class="lg:pl-12" aria-label="{{ __('Product') }}">
          <h3 class="mb-7 text-sm font-bold tracking-[0.14em] uppercase">{{ __('Product') }}</h3>
          <ul class="flex flex-col gap-4 text-lg text-neutral-0/65">
            @foreach (array_slice($ungrouped, 0, (int) ceil(count($ungrouped) / 2)) as $item)
              @if ($item['is_visible'] ?? true)
                <li><a href="{{ $legalFooterUrl($item['label'], $item['url'] ?? null) }}" class="transition-colors hover:text-neutral-0">{{ $item['label'] }}</a></li>
              @endif
            @endforeach
          </ul>
        </nav>
      @endif

      @if ($companyGroup)
        <nav class="lg:pl-12" aria-label="{{ __('Company') }}">
          <h3 class="mb-7 text-sm font-bold tracking-[0.14em] uppercase">{{ $companyGroup['label'] }}</h3>
          <ul class="flex flex-col gap-4 text-lg text-neutral-0/65">
            @foreach ($companyGroup['children'] as $item)
              @if ($item['is_visible'] ?? true)
                <li><a href="{{ $legalFooterUrl($item['label'], $item['url'] ?? null) }}" class="transition-colors hover:text-neutral-0">{{ $item['label'] }}</a></li>
              @endif
            @endforeach
          </ul>
        </nav>
      @elseif (!empty($ungrouped))
        <nav class="lg:pl-12" aria-label="{{ __('Company') }}">
          <h3 class="mb-7 text-sm font-bold tracking-[0.14em] uppercase">{{ __('Company') }}</h3>
          <ul class="flex flex-col gap-4 text-lg text-neutral-0/65">
            @foreach (array_slice($ungrouped, (int) ceil(count($ungrouped) / 2)) as $item)
              @if ($item['is_visible'] ?? true)
                <li><a href="{{ $legalFooterUrl($item['label'], $item['url'] ?? null) }}" class="transition-colors hover:text-neutral-0">{{ $item['label'] }}</a></li>
              @endif
            @endforeach
          </ul>
        </nav>
      @endif

      <div class="lg:pl-12">
        <h3 class="mb-7 text-sm font-bold tracking-[0.14em] uppercase">{{ $themeVars['footer_newsletter_heading'] ?? __('Newsletter') }}</h3>
        <p class="mb-6 text-lg text-neutral-0/65">{{ $themeVars['footer_newsletter_subheading'] ?? __('Subscribe to our newsletter') }}</p>
        <form method="POST" action="{{ route('newsletter.subscribe', [], false) }}" class="relative max-w-md" data-newsletter>
          <input type="hidden" name="_token" value="{{ csrf_token() }}">
          <input
            name="email"
            type="email"
            required
            placeholder="{{ __('Your email ...') }}"
            aria-label="{{ __('Your email') }}"
            class="w-full rounded-full border border-neutral-0/10 bg-neutral-0/6 py-4 pr-16 pl-6 text-base text-neutral-0 transition-colors placeholder:text-neutral-0/45 focus:border-accent/60 focus:outline-none"
          />
          <button
            type="submit"
            aria-label="{{ __('Subscribe') }}"
            class="absolute top-1/2 right-2 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-accent text-deep transition-colors hover:bg-primary hover:text-neutral-0"
          >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
          </button>
        </form>
        <p class="mt-3 text-sm text-accent" data-newsletter-note>{{ session('newsletter_success') }}</p>
      </div>
    </div>

    <div class="flex flex-col gap-6 border-t border-neutral-0/10 py-8 md:flex-row md:items-center md:justify-between">
      <p class="order-2 text-base text-neutral-0/55 md:order-1">
        {{ strip_tags($themeVars['footer_copyright'] ?? __('Copyright :year :name. All rights reserved.', ['year' => date('Y'), 'name' => ($themeVars['logo_text'] ?? 'WaPro')])) }}
      </p>

      <div class="order-1 flex items-center gap-3 md:order-2">
        @if ($themeVars['footer_social_facebook'] ?? false)
          <a href="{{ $themeVars['footer_social_facebook'] }}" aria-label="{{ __('Facebook') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-accent text-deep transition-colors hover:bg-primary hover:text-neutral-0">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 9h3l.5-3H14V4.5c0-.9.3-1.5 1.6-1.5H17.5V.2C17.1.1 16 0 14.8 0 12.2 0 10.5 1.6 10.5 4.4V6H8v3h2.5v9H14V9Z"/></svg>
          </a>
        @endif
        @if ($themeVars['footer_social_x'] ?? false)
          <a href="{{ $themeVars['footer_social_x'] }}" aria-label="{{ __('X') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-accent text-deep transition-colors hover:bg-primary hover:text-neutral-0">
            <svg class="h-[14px] w-[14px]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.2 2h3.3l-7.2 8.3L23 22h-6.6l-5.2-6.8L5.3 22H2l7.7-8.8L1.4 2H8l4.7 6.2L18.2 2Zm-1.2 18h1.8L7.1 3.8H5.2L17 20Z"/></svg>
          </a>
        @endif
        @if ($themeVars['footer_social_linkedin'] ?? false)
          <a href="{{ $themeVars['footer_social_linkedin'] }}" aria-label="{{ __('LinkedIn') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-accent text-deep transition-colors hover:bg-primary hover:text-neutral-0">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5A2.5 2.5 0 1 1 0 3.5a2.5 2.5 0 0 1 4.98 0ZM.5 8h4V24h-4V8Zm6.5 0h3.8v2.2h.05c.53-1 1.83-2.2 3.77-2.2 4.03 0 4.78 2.65 4.78 6.1V24h-4v-7.1c0-1.7-.03-3.9-2.37-3.9-2.38 0-2.74 1.85-2.74 3.77V24h-4V8Z"/></svg>
          </a>
        @endif
      </div>

      <div class="order-3 flex items-center gap-4 text-base text-neutral-0/55">
        <a href="{{ $confidentialityPrivacyUrl }}" class="transition-colors hover:text-neutral-0">{{ __('Confidentiality & Privacy') }}</a>
        <span class="text-neutral-0/25" aria-hidden="true">|</span>
        <a href="{{ $legalInformationUrl }}" class="transition-colors hover:text-neutral-0">{{ __('Legal Information') }}</a>
        @if (filled($cookiePolicyUrl) && $cookiePolicyUrl !== '#')
          <span class="text-neutral-0/25" aria-hidden="true">|</span>
          <a href="{{ $cookiePolicyUrl }}" class="transition-colors hover:text-neutral-0">{{ __('Cookie Policy') }}</a>
        @endif
      </div>
    </div>
  </div>
</footer>
