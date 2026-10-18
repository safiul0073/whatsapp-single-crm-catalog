@php
    $authUser = auth()->user();
    $siteName = 'WaPro';
    $navigation = app(\App\Services\Navigation\WorkspaceNavigationRegistry::class)->forUser($authUser);
    $modules = $navigation['modules'];
    $activeModule = $navigation['active_module_data'];
    $hasRouteContext = $navigation['has_route_context'];

    $coreWorkspaceRoutes = [
        ['label' => __('CRM'), 'route' => 'user.crm.index'],
        ['label' => __('Templates'), 'route' => 'user.message-templates.index'],
    ];
@endphp

<div id="appSidebarBackdrop" class="app-backdrop lg:hidden"></div>

<aside id="appSidebar" class="app-sidebar" aria-label="{{ __('User navigation') }}">
    <div class="app-sidebar__brand" aria-label="{{ $siteName }}">
        <a href="{{ route('user.dashboard') }}" class="flex min-w-0 items-center gap-2.5">
            @if (setting('site_logo') && media_url(setting('site_logo')))
                <img src="{{ media_url(setting('site_logo')) }}" alt="{{ $siteName }}"
                    class="h-9 w-9 shrink-0 rounded-md object-cover">
            @else
                <span
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-primary text-neutral-0 shadow-[0_6px_16px_-6px_rgba(31,170,83,0.7)]">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" />
                    </svg>
                </span>
            @endif
            <span class="app-sidebar__brand-text truncate font-title text-lg font-extrabold tracking-tight text-title">
                {{ $siteName }}
            </span>
        </a>

        <button type="button" id="appSidebarClose"
            class="ms-auto grid h-8 w-8 place-items-center rounded-sm text-body hover:bg-neutral-100 hover:text-title lg:hidden"
            aria-label="{{ __('Close sidebar') }}">
            <i class="ph ph-x text-lg"></i>
        </button>
    </div>

    @if (!empty($modules))
        <div class="app-sidebar__modules" aria-label="{{ __('Switch module') }}">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($modules as $module)
                    <button type="button"
                        class="app-sidebar__module-btn {{ $module['is_active'] ? 'is-active' : '' }}"
                        data-module-switch="{{ $module['id'] }}"
                        data-module-label="{{ $module['label'] }}"
                        data-module-icon="{{ $module['icon'] }}"
                        aria-label="{{ $module['label'] }}">
                        <i class="ph {{ $module['icon'] }} text-sm"></i>
                        <span>{{ $module['short_label'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <nav class="app-nav scrollbar-hide flex-1 overflow-y-auto px-3 py-4" aria-label="{{ __('Sidebar navigation') }}">
        <div id="appSidebarFavorites" class="app-nav__favorites is-hidden mb-3 border-b border-neutral-100 pb-3">
            <p class="app-nav__group">{{ __('Favorites') }}</p>
            <div id="appSidebarFavoritesList" class="space-y-0.5"></div>
        </div>

        @foreach ($modules as $module)
            <div class="app-nav__module-panel {{ ($module['is_active'] || ! $hasRouteContext) ? '' : 'is-hidden' }}"
                data-module-panel="{{ $module['id'] }}">
                @foreach ($module['groups'] as $group)
                    <p class="app-nav__group">{{ $group['label'] }}</p>
                    <div class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            <div class="app-nav__item group/nav relative flex items-center">
                                <a href="{{ $item['url'] }}"
                                    class="app-nav__link flex-1 {{ $item['is_active'] ? 'is-active' : '' }}"
                                    aria-label="{{ $item['label'] }}"
                                    data-nav-route="{{ $item['route'] }}"
                                    data-nav-label="{{ $item['label'] }}"
                                    data-nav-icon="{{ $item['icon'] }}"
                                    data-nav-module="{{ $module['id'] }}">
                                    <span class="relative inline-grid shrink-0 place-items-center">
                                        <i class="ph {{ $item['icon'] }} text-xl"></i>
                                        @if (($item['badge'] ?? null) === 'notifications' && ($sidebarUnreadCount ?? 0) > 0)
                                            <span class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-error"></span>
                                        @endif
                                    </span>
                                    <span class="app-nav__text truncate">{{ $item['label'] }}</span>
                                </a>
                                <button type="button"
                                    class="app-nav__pin"
                                    data-action="toggle-favorite"
                                    data-fav-route="{{ $item['route'] }}"
                                    aria-label="{{ __('Pin to favorites') }}"
                                    title="{{ __('Pin to favorites') }}">
                                    <i class="ph ph-push-pin text-xs"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="app-sidebar__user">
        <div class="flex items-center gap-3 rounded-md bg-section p-2.5">
            <span
                class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-deep text-sm font-bold text-neutral-0">
                {{ strtoupper(substr($authUser->name ?? 'U', 0, 2)) }}
            </span>
            <div class="app-sidebar__user-meta min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-title">{{ $authUser->name ?? __('User') }}</p>
                <p class="truncate text-xs text-body">{{ $authUser->email ?? '' }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="app-sidebar__logout">
                @csrf
                <button type="submit"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-sm text-body transition-colors hover:bg-neutral-100 hover:text-error"
                    aria-label="{{ __('Sign out') }}">
                    <i class="ph ph-sign-out text-lg"></i>
                </button>
            </form>
        </div>
    </div>
</aside>
