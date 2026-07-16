@php
    // Use the view-shared $panelConfig (from PanelAccess middleware) or fall back to the singleton
    $panelConfig = $panelConfig ?? (app('current.panel') ?? []);
    $navigation = $panelConfig['navigation'] ?? [];

    // Group navigation items by their 'group' key
    $groups = [];
    foreach ($navigation as $item) {
        $groupName = $item['group'] ?? 'Main Menu';
        $groups[$groupName][] = $item;
    }
@endphp

<div id="appSidebarBackdrop" class="app-backdrop lg:hidden"></div>

<aside id="appSidebar" class="app-sidebar">

    {{-- Logo Section --}}
    <div class="app-sidebar__brand" aria-label="{{ setting('site_name', config('app.name', 'WaPro')) }}">
        <a href="{{ ($panel ?? 'user') === 'admin' ? route('admin.dashboard') : url('/') }}"
            class="flex items-center gap-2.5">
            {{-- Logo Icon --}}
            @if (setting('site_logo') && media_url(setting('site_logo')))
                <img src="{{ media_url(setting('site_logo')) }}"
                    alt="{{ setting('site_name', config('app.name', 'WaPro')) }}"
                    class="h-9 w-9 shrink-0 rounded-xl object-cover">
            @else
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary text-neutral-0 shadow-[0_6px_16px_-6px_rgba(31,170,83,0.7)]">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" />
                    </svg>
                </span>
            @endif

            {{-- App Name --}}
            <span class="font-title text-lg font-extrabold tracking-tight text-title">
                {{ setting('site_name', config('app.name', 'WaPro')) }}
            </span>
            @if (($panel ?? 'user') === 'admin')
                <span class="ml-0.5 inline-flex rounded-full bg-section px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">
                    {{ __('Admin') }}
                </span>
            @endif
        </a>

        {{-- Mobile Close Button --}}
        <button type="button"
            id="appSidebarClose"
            class="ms-auto grid h-8 w-8 place-items-center rounded-lg text-body hover:bg-neutral-100 hover:text-title lg:hidden"
            aria-label="Close Sidebar">
            <i class="ph ph-x text-lg"></i>
        </button>
    </div>

    {{-- Navigation Container --}}
    <div class="app-nav scrollbar-hide flex-1 overflow-y-auto px-3 py-4">

        {{-- Navigation Groups --}}
        @foreach ($groups as $groupTitle => $items)
            <p class="app-nav__group">{{ __($groupTitle) }}</p>
            <div class="space-y-0.5">
                @foreach ($items as $item)
                    <x-navigation.sidebar-item :label="$item['label'] ?? ''" :icon="$item['icon'] ?? 'ph-circle'" :route="$item['route'] ?? ''" :permission="$item['permission'] ?? null"
                        :children="$item['children'] ?? []" />
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- User Footer --}}
    <div class="app-sidebar__user">
        @php
            $panelKey = $panel ?? 'user';
            $logoutRoute = $panelKey === 'admin' ? route('admin.logout') : route('logout');
            $currentUser = $panelKey === 'admin' ? auth('admin')->user() : auth()->user();
        @endphp
        <div class="flex items-center gap-3 rounded-xl bg-section p-2.5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-deep text-sm font-bold text-neutral-0">
                {{ strtoupper(substr($currentUser->name ?? 'AD', 0, 2)) }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-title">{{ $currentUser->name ?? __('Admin Owner') }}</p>
                <p class="truncate text-xs text-body">{{ $currentUser->email ?? 'admin@wapro.app' }}</p>
            </div>
            <form method="POST" action="{{ $logoutRoute }}" id="sidebar-logout-form">
                @csrf
                <button type="submit"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-body transition-colors hover:bg-neutral-100 hover:text-error"
                    aria-label="Logout">
                    <i class="ph ph-sign-out text-lg"></i>
                </button>
            </form>
        </div>
    </div>
</aside>
