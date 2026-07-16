@php
    $headerMenu = $resolvedMenus['header'] ?? null;
    $headerItemsRaw = $headerMenu['items'] ?? [];

    $flatten = function (array $items) use (&$flatten): array {
        $flat = [];
        foreach ($items as $item) {
            $flat[] = $item;
            if (! empty($item['children'])) {
                $flat = array_merge($flat, $flatten($item['children']));
            }
        }
        return $flat;
    };

    $headerItems = $flatten($headerItemsRaw);
    $isUserSignedIn = auth('web')->check();

@endphp

<header class="sticky top-0 z-[100] border-b border-neutral-200/70 bg-neutral-0/85 backdrop-blur-lg">
  <div class="container">
    <div class="flex h-[68px] items-center justify-between gap-4">
      <a href="{{ route('home') }}" class="flex items-center gap-2.5">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary text-neutral-0 shadow-[0_6px_16px_-6px_rgba(31,170,83,0.7)]">
          <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zM9.5 7a.5.5 0 0 1 .5.5c0 .7.1 1.4.3 2 .1.3 0 .6-.2.8l-.8.9a8.5 8.5 0 0 0 3.5 3.5l.9-.8c.2-.2.5-.3.8-.2.6.2 1.3.3 2 .3a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5A9 9 0 0 1 7 12a.5.5 0 0 1 .5-.5h2z"/></svg>
        </span>
        <span class="font-title text-xl font-extrabold tracking-tight text-title">{{ $themeVars['logo_text'] ?? 'WaPro' }}</span>
      </a>

      <nav class="hidden items-center gap-1 lg:flex">
        @foreach ($headerItems as $item)
          @if ($item['is_visible'] ?? true)
            <a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}" class="nav-link">{{ $item['label'] }}</a>
          @endif
        @endforeach
      </nav>

      <div class="relative z-[101] flex items-center gap-2">
        @if ($themeVars['show_auth_links'] ?? true)
          @if ($isUserSignedIn)
            <a href="{{ route('user.dashboard') }}" class="btn-sm btn-primary pointer-events-auto">{{ __('Open Dashboard') }}</a>
          @else
            <a href="{{ route('login') }}" data-auth-nav-link class="btn-sm btn-primary pointer-events-auto">{{ __('Sign in') }}</a>
          @endif
        @endif
        <button id="mobileNavOpen" type="button" class="mobile-nav-toggle btn-ghost lg:hidden" aria-label="{{ __('Open menu') }}" aria-controls="mobileNav" aria-expanded="false">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
      </div>
    </div>
  </div>

  <div id="mobileNav" class="mobile-nav lg:hidden">
    <nav class="container flex flex-col gap-1 py-4">
      @foreach ($headerItems as $item)
        @if ($item['is_visible'] ?? true)
          <a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}" class="mobile-nav-link">{{ $item['label'] }}</a>
        @endif
      @endforeach
      @if ($themeVars['show_auth_links'] ?? true)
        @if ($isUserSignedIn)
          <a href="{{ route('user.dashboard') }}" class="mt-2 rounded-lg px-3 py-2.5 text-sm font-medium text-body hover:bg-neutral-100 sm:hidden">{{ __('Open Dashboard') }}</a>
        @else
          <a href="{{ route('login') }}" data-auth-nav-link class="mt-2 rounded-lg px-3 py-2.5 text-sm font-medium text-body hover:bg-neutral-100 sm:hidden">{{ $themeVars['sign_in_text'] ?? __('Sign in') }}</a>
        @endif
      @endif
    </nav>
  </div>
</header>
